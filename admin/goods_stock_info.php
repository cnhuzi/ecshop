<?php

/**
 *  管理中心采购商品清单列表管理处理程序文件
 * ============================================================================
 * * 版权所有 2005-2012 新泛联
 * 网站地址: http://www..com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * m1 SouthBear 2014-12-11 增加采购订单查询条件
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/*初始化数据交换对象 */
$exc   = new exchange($ecs->table("stock_info"), $db, 'id', '');


/*------------------------------------------------------ */
//-- 采购商品清单列表管理
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    admin_priv('goods_stock_info');
    /* 取得过滤条件 */
    $filter = array();
    $smarty->assign('ur_here',      $_LANG['08_goods_stock_info_list']);
    $smarty->assign('full_page',    1);
    $smarty->assign('filter',       $filter);

    /* 代理商列表 */
    $arr_res = agency_list();
    $GLOBALS['smarty']->assign('agency_list',   $arr_res);
    /*判断代理商或管理员*/
    if(if_agency())
    {
        $smarty->assign('if_agency',       if_agency());
    }
    $get_goods_stock_info = get_goods_stock_info();
    
    $smarty->assign('admin_id',       $_SESSION[admin_id]);

    $smarty->assign('get_goods_stock_info',    $get_goods_stock_info['arr']);
    $smarty->assign('filter',          $get_goods_stock_info['filter']);
    $smarty->assign('record_count',    $get_goods_stock_info['record_count']);
    $smarty->assign('page_count',      $get_goods_stock_info['page_count']);

    $sort_flag  = sort_flag($get_goods_stock_info['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    assign_query_info();
    $smarty->display('goods_stock_info.htm');
}

/*------------------------------------------------------ */
//-- 翻页，排序
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    check_authz_json('goods_stock_info');

    $get_goods_stock_info = get_goods_stock_info();
	
    $smarty->assign('get_goods_stock_info',    $get_goods_stock_info['arr']);
    $smarty->assign('filter',          $get_goods_stock_info['filter']);
    $smarty->assign('record_count',    $get_goods_stock_info['record_count']);
    $smarty->assign('page_count',      $get_goods_stock_info['page_count']);

    $sort_flag  = sort_flag($get_goods_stock_info['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('goods_stock_info.htm'), '',
    array('filter' => $get_goods_stock_info['filter'], 'page_count' => $get_goods_stock_info['page_count']));
}

elseif ($_REQUEST['act'] == 'remove')
{
    $id = intval($_REQUEST['id']);
    /* 检查权限 */
    check_authz_json('remove_back');   
    $stock_info_sn = $GLOBALS['db']->getOne("SELECT stock_info_sn FROM " .$ecs->table('stock_info'). " WHERE id = $id ");
    
    $sql = "UPDATE " .$ecs->table('stock_info'). " SET keywords = '$keywords' WHERE goods_id = '$goods_id' LIMIT 1";
    $db->query($sql);

    clear_cache_files();
    $url = 'goods_stock_info.php?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);
    ecs_header("Location: $url\n");
    exit;
}

// ccx 对采购订单进行采购审核 
elseif($_REQUEST['act'] == 'handle')
{
    /* 检查权限 */
    admin_priv('stock_ruku_handle');
    $id = $_REQUEST['id'];   
    $stock_status = $_REQUEST['stock_status'];
    $stock_info_sn  = $GLOBALS['db']->getOne("SELECT stock_info_sn FROM ".$ecs->table('stock_info')." WHERE id = $id  limit 1 " );
    
    if($stock_status ==1 )
    {
        /* ccx 2014-12-09 获取入库清单的单号*/
        //更新采购清单的订单状态
        $GLOBALS['db']->query("UPDATE " .$ecs->table('stock_info'). " SET stock_status = $stock_status WHERE id = $id");
        // 更新采购商品表的商品的采购状态
        $GLOBALS['db']->query("UPDATE " .$ecs->table('stock_goods'). " SET stock_status = $stock_status WHERE stock_info_id = $id ");
        /*ccx 2014-12-09 写入相应的日志记录*/
       admin_log($stock_info_sn, 'shenhe', 'stock_info');
    }
    elseif($stock_status ==2)  // 采购订单进行入库
    {
		$GLOBALS['db']->begin();   //开始事务
        //更新采购清单的订单状态
        $GLOBALS['db']->query("UPDATE " .$ecs->table('stock_info'). " SET stock_status = $stock_status WHERE id = $id");
        // 更新采购商品表的商品的采购状态
        $GLOBALS['db']->query("UPDATE " .$ecs->table('stock_goods'). " SET stock_status = $stock_status WHERE stock_info_id = $id ");
        
        $stock_goods_list = $GLOBALS['db']->getAll("select goods_id, goods_name, costing_price, goods_number, stock_info_sn, admin_agency_id from " . $ecs->table('stock_goods') ." where stock_info_id = $id");
        foreach($stock_goods_list as $costing_key=>$costing_value)
        {
			$goods_number_old  = $GLOBALS['db']->getRow("SELECT goods_number, id FROM ".$GLOBALS['ecs']->table('stock_control')." WHERE goods_id = '".$costing_value['goods_id']."' AND costing_price = '".$costing_value['costing_price']."' ");
			//var_dump($goods_number_old);
			if (empty($goods_number_old) === true)  //没有相同的产品相同的价格
			{
			$sql = "INSERT INTO " . $GLOBALS['ecs']->table('stock_control') . " (goods_id, goods_name, log_time, goods_number, 			costing_price, admin_agency_id )  VALUES ('" . $costing_value['goods_id'] . "', '".$costing_value['goods_name']."', '". gmtime() ."', '" . $costing_value['goods_number'] . "' , '".$costing_value['costing_price']."' , '".$costing_value['admin_agency_id']."')";
            $GLOBALS['db']->query($sql);
			$stock_control_id = $GLOBALS['db']->insert_id();  //返回stock_stock_control 所产生的最新的id
			}
			else
			{
			$goods_number_new = $goods_number_old['goods_number'] + $costing_value['goods_number'] ; 
			$GLOBALS['db']->query("UPDATE " . $ecs->table('stock_control') . " SET log_time = '". gmtime() ."', goods_number = '". $goods_number_new ."' WHERE id='".$goods_number_old['id']."'");
	        $stock_control_id = $goods_number_old['id']; 
			}
             
            $stock_type = 1 ;    //商品入库处理， 默认为 1（增加）  -1（减少）
            $stock_status = 1;   //1:添加入库产品，2：发货时候库存减少状态，3：库存不够的时候，4 退货的时候库存会增加状态）
            //写入相关的入库数据记录
            $sql_log = "INSERT INTO " . $GLOBALS['ecs']->table('stock_control_log') . " (stock_id, goods_name, log_time, goods_number, stock_type, costing_price, stock_number, stock_status, stock_note, ip_address, admin_agency_id )  VALUES ('" . $stock_control_id . "', '".$costing_value['goods_name']."', '".gmtime()."', '". $costing_value['goods_number'] ."', '". $stock_type ."', '" . $costing_value['costing_price'] . "' , '".$costing_value['stock_info_sn']."' , '".$stock_status."', '".real_ip()."','". real_ip() ."', '".$costing_value['admin_agency_id']."')";
            $GLOBALS['db']->query($sql_log);
            
            //入库成功之后， 商品的总的库存数量也要相应的增加
            $goods_number_old  = $GLOBALS['db']->getOne("SELECT goods_number FROM ".$GLOBALS['ecs']->table('goods')." WHERE goods_id = '".$costing_value['goods_id']."' ");
            $goods_num = $goods_number_old + $costing_value['goods_number'] ;
            $GLOBALS['db']->query("UPDATE " .$ecs->table('goods'). " SET goods_number = '".$goods_num."' WHERE goods_id = '".$costing_value['goods_id']."' ");
        }
        $sql_delete = "DELETE FROM " . $ecs->table('stock_goods') .
                      " WHERE stock_info_id = '$id'";
        $GLOBALS['db']->query($sql_delete);
        $GLOBALS['db']->commit();  
        /*ccx 2014-12-09 写入相应的日志记录*/
        admin_log($stock_info_sn, 'ruku', 'stock_info'); 
    }
    elseif($stock_status ==3)  // 取消商品采购
    {
        //更新采购清单的订单状态
        $GLOBALS['db']->query("UPDATE " .$ecs->table('stock_info'). " SET stock_status = $stock_status WHERE id = $id");
        // 更新采购商品表的商品的采购状态
        $GLOBALS['db']->query("UPDATE " .$ecs->table('stock_goods'). " SET stock_status = $stock_status WHERE stock_info_id = $id ");
        
       /*ccx 2014-12-09 写入相应的日志记录*/
       admin_log($stock_info_sn, 'zuofei', 'stock_info');
    }
    
   
    clear_cache_files();   
    $url = 'goods_stock_info.php?act=list';
    ecs_header("Location: $url\n");
}



/* 获取采购商品列表管理列表 */
function get_goods_stock_info()
{
    $result = get_filter();

    if ($result === false)
    {
        $filter = array();
        $filter['admin_agency_id']    = empty($_REQUEST['admin_agency_id']) ? '' : trim($_REQUEST['admin_agency_id']);
        $filter['keyword']    = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
        $filter['status'] = strval(trim($_REQUEST['status'])); //m1
       
        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keyword'] = json_str_iconv($filter['keyword']);
        }
        $filter['sort_by']    = empty($_REQUEST['sort_by']) ? 'a.id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $where = agency_where();
        /*add by hg for date 2014-04-21 可选商品*/
        if(if_agency()){
            if (!empty($filter['admin_agency_id']))
            {
                $where .= " AND (a.admin_agency_id = $filter[admin_agency_id] ) ";
            }
            else
            {
                $where .= " AND (a.admin_agency_id = 0 ) ";
            }
        }
            
        //m1 增加采购订单查询条件
        if (strlen(trim($_REQUEST['status'])) > 0) {
        	$where .= " AND (a.stock_status = ".$filter['status']." ) ";
        }
            
        if (!empty($filter['keyword']))
        {
            $where .= " AND (a.stock_info_sn LIKE '%" . mysql_like_quote($filter['keyword']) . "%' )";
        }
        
        $sql = 'SELECT COUNT(*) FROM ' .$GLOBALS['ecs']->table('stock_info'). ' AS a '.
               'WHERE 1 ' .$where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        $filter = page_and_size($filter);

        /* 获取采购商品列表管理数据 */
        $sql = 'SELECT a.*  '.
               'FROM ' .$GLOBALS['ecs']->table('stock_info'). ' AS a '.
               'WHERE 1 ' .$where. ' ORDER by '.$filter['sort_by'].' '.$filter['sort_order'];

        $filter['keyword'] = stripslashes($filter['keyword']);
        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }
 //echo $sql.'<br/>';
    $arr = array();
    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res))
    {
        $arr[] = $rows;
    }
    
    //显示采购订单的相关信息：包括库存数量，以及采购总价，当页的采购价格等等
    $sql_limit = $sql. " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";
    $overall_order_amount = overall_order_amount($sql);  //总的计算
    $current_order_amount = current_order_amount($sql_limit); // 当前页的计算
    
    $GLOBALS['smarty']->assign('costing_price_amount',price_format($overall_order_amount['costing_price_amount']));
    $GLOBALS['smarty']->assign('goods_number_amount',$overall_order_amount['goods_number_amount']);
    $GLOBALS['smarty']->assign('current_costing_price_amount',price_format($current_order_amount['current_costing_price_amount']));
    $GLOBALS['smarty']->assign('current_goods_number_amount',$current_order_amount['current_goods_number_amount']);
    
    return array('arr' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

function overall_order_amount($sql)
{
	$row = $GLOBALS['db']->getAll($sql);
	$arr = array('costing_price_amount'=>'');
	if(!empty($row))
	foreach($row as $k=>$v){
		$arr['costing_price_amount'] += $v['goods_amount'];
	}
	return $arr;
}
function current_order_amount($sql_limit)
{
	$row = $GLOBALS['db']->getAll($sql_limit);
	$arr = array('current_costing_price_amount'=>'');
	if(!empty($row))
	foreach($row as $k=>$v){
		$arr['current_costing_price_amount'] += $v['goods_amount'] ;
	}
	return $arr;
}


?>