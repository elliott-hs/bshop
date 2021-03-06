<?php
/**
 * @brief 商家模块
 * @class Seller
 * @author chendeshan
 * @datetime 2014/7/19 15:18:56
 */
class Seller extends IController implements sellerAuthorization
{
	public $layout = 'seller';
	private $dat = array();

	/**
	 * @brief 初始化检查
	 */
	public function init()
	{

	}
	/**
	 * @brief 商品添加中图片上传的方法
	 */
	public function goods_img_upload()
	{
	 	//调用文件上传类
		$photoObj = new PhotoUpload();
		$photo    = current($photoObj->run());

		//判断上传是否成功，如果float=1则成功
		if($photo['flag'] == 1)
		{
			$result = array(
				'flag'=> 1,
				'img' => $photo['img']
			);
		}
		else
		{
			$result = array('flag'=> $photo['flag']);
		}
		echo JSON::encode($result);
	}
	/**
	 * @brief 商品添加和修改视图
	 */
	public function goods_edit()
	{
		$goods_id = IFilter::act(IReq::get('id'),'int');

		//初始化数据
		$goods_class = new goods_class($this->seller['seller_id']);

		//获取所有商品扩展相关数据
		$data = $goods_class->edit($goods_id);

		if($goods_id && !$data)
		{
			die("没有找到相关商品！");
		}

		$this->setRenderData($data);
		$this->redirect('goods_edit');
	}
	//商品更新动作
	public function goods_update()
	{
		$id       = IFilter::act(IReq::get('id'),'int');
		$callback = IFilter::act(IReq::get('callback'));
		$callback = strpos($callback,'seller/goods_list') === false ? '' : $callback;

		//检查表单提交状态
		if(!$_POST)
		{
			die('请确认表单提交正确');
		}

		//初始化商品数据
		unset($_POST['id']);
		unset($_POST['callback']);

		$goodsObject = new goods_class($this->seller['seller_id']);
		$goodsObject->update($id,$_POST);

		$callback ? $this->redirect($callback) : $this->redirect("goods_list");
	}

	//商品分类列表
	public function category_list(){
		$isCache = false;
		$tb_category = new IModel('category');
		$cacheObj = new ICache('file');
		$data = $cacheObj->get('sortdata');
		if(!$data)
		{
			$goods = new goods_class();
			$data = $goods->sortdata($tb_category->query('seller_id='.$this->seller['seller_id'],'*','sort asc'));
			$isCache ? $cacheObj->set('sortdata',$data) : "";
		}
		$datas['category'] = $data;
		$this->setRenderData($datas);
		$this->redirect('category_list',false);
	}

	/**
	 * @brief 商品分类添加、修改
	 */
	function category_edit()
	{
		$category_id = IFilter::act(IReq::get('cid'),'int');
		if($category_id)
		{
			$categoryObj = new IModel('category');
			$this->categoryRow = $categoryObj->getObj('id = '.$category_id);
		}
		$this->setRenderData($this->seller);
		$this->redirect('category_edit');
	}
		/**
	 * @brief 保存商品分类
	 */
	function category_save()
	{
		//获得post值
		$category_id = IFilter::act(IReq::get('id'),'int');
		$name = IFilter::act(IReq::get('name'));
		$parent_id = IFilter::act(IReq::get('parent_id'),'int');
		$visibility = IFilter::act(IReq::get('visibility'),'int');
		$sort = IFilter::act(IReq::get('sort'),'int');
		$title = IFilter::act(IReq::get('title'));
		$keywords = IFilter::act(IReq::get('keywords'));
		$descript = IFilter::act(IReq::get('descript'));

		if(!$name)
		{
			$this->redirect('category_list');
		}

		$tb_category = new IModel('category');
		$category_info = array(
			'name'      => $name,
			'parent_id' => $parent_id,
			'sort'      => $sort,
			'visibility'=> $visibility,
			'keywords'  => $keywords,
			'descript'  => $descript,
			'title'     => $title,
			'seller_id' => $this->seller['seller_id'],
		);
		$tb_category->setData($category_info);
		if($category_id)									//保存修改分类信息
		{
			$where = "id=".$category_id;
			$tb_category->update($where);
		}
		else												//添加新商品分类
		{
			$tb_category->add();
		}
		$this->redirect('category_list');
	}


		/**
	 * @brief 删除商品分类
	 */
	function category_del()
	{
		$category_id = IFilter::act(IReq::get('cid'),'int');
		$seller_id = IFilter::act(IReq::get('seller_id'),'int');
		if($category_id&&$seller_id)
		{
			$tb_category = new IModel('category');
			$catRow      = $tb_category->getObj('seller_id='.$seller_id.' and parent_id = '.$category_id);

			//要删除的分类下还有子节点
			if(!empty($catRow))
			{
				$this->category_list();
				Util::showMessage('无法删除此分类，此分类下还有子分类，或者回收站内还留有子分类');
				exit;
			}

			if($tb_category->del('seller_id='.$seller_id.' and id = '.$category_id))
			{
				$tb_category_extend  = new IModel('category_extend');
				$tb_category_extend->del('category_id = '.$category_id);
				$this->redirect('category_list');
			}
			else
			{
				$this->category_list();
				$msg = "没有找到相关分类记录！";
				Util::showMessage($msg);
			}
		}
		else
		{
			$this->category_list();
			$msg = "没有找到相关分类记录！";
			Util::showMessage($msg);
		}
	}

	//商品列表
	public function goods_list()
	{
		$seller_id = $this->seller['seller_id'];
		$searchArray = Util::getUrlParam('search');
		$searchParam = http_build_query($searchArray);
		$condition = Util::goodsSearch(IReq::get('search'));
		$where = "go.seller_id='$seller_id' ";
		$where .= $condition ? " and ".$condition : "";
		$join = isset($searchArray['search']['category_id']) ? " left join category_extend as ce on ce.goods_id = go.id " : "";
		$page   = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;

		$goodHandle = new IQuery('goods as go');
		$goodHandle->order  = "go.id desc";
		$goodHandle->fields = "distinct go.id,go.name,go.sell_price,go.market_price,go.store_nums,go.img,go.is_del,go.seller_id,go.is_share,go.sort";
		$goodHandle->where  = $where;
		$goodHandle->page	= $page;
		$goodHandle->join	= $join;

		$this->goodHandle = $goodHandle;

		$goods_info = array();
		$goods_info['seller_id'] = $seller_id;
		$goods_info['searchParam'] = $searchParam;
		$this->setRenderData($goods_info);
		$this->redirect('goods_list');
	}

	//商品列表
	public function goods_report()
	{
		$seller_id = $this->seller['seller_id'];
		$condition = Util::goodsSearch(IReq::get('search'));

		$where  = 'go.seller_id='.$seller_id;
		$where .= $condition ? " and ".$condition : "";
		$join = isset($_GET['search']['category_id']) ? " left join category_extend as ce on ce.goods_id = go.id " : "";

		$goodHandle = new IQuery('goods as go');
		$goodHandle->order  = "go.id desc";
		$goodHandle->fields = "go.*";
		$goodHandle->where  = $where;
		$goodHandle->join	= $join;
		$goodList = $goodHandle->find();

		//构建 Excel table;
		$strTable ='<table width="500" border="1">';
		$strTable .= '<tr>';
		$strTable .= '<td style="text-align:center;font-size:12px;">商品名称</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="160">分类</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="60">售价</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="60">库存</td>';
		$strTable .= '</tr>';

		foreach($goodList as $k=>$val){
			$strTable .= '<tr>';
			$strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['name'].'</td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.goods_class::getGoodsCategory($val['id']).' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['sell_price'].' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['store_nums'].' </td>';
			$strTable .= '</tr>';
		}
		$strTable .='</table>';
		unset($goodList);
		$reportObj = new report();
		$reportObj->setFileName('goods');
		$reportObj->toDownload($strTable);
		exit();
	}

	//商品删除
	public function goods_del()
	{
		//post数据
	    $id = IFilter::act(IReq::get('id'),'int');

	    //生成goods对象
	    $goods = new goods_class();
	    $goods->seller_id = $this->seller['seller_id'];

	    if($id)
		{
			if(is_array($id))
			{
				foreach($id as $key => $val)
				{
					$goods->del($val);
				}
			}
			else
			{
				$goods->del($id);
			}
		}
		$this->redirect("goods_list");
	}


	//商品状态修改
	public function goods_status()
	{
	    $id        = IFilter::act(IReq::get('id'),'int');
		$is_del    = IFilter::act(IReq::get('is_del'),'int');
		$is_del    = $is_del === 0 ? 3 : $is_del; //不能等于0直接上架
		$seller_id = $this->seller['seller_id'];

		$goodsDB = new IModel('goods');
		$goodsDB->setData(array('is_del' => $is_del));

	    if($id)
		{
			if(is_array($id))
			{
				foreach($id as $key => $val)
				{
					$goodsDB->update("id = ".$val." and seller_id = ".$seller_id);
				}
			}
			else
			{
				$goodsDB->update("id = ".$val." and seller_id = ".$seller_id);
			}
		}
		$this->redirect("goods_list");
	}

	//规格删除
	public function spec_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');

		if($id)
		{
			$idString = is_array($id) ? join(',',$id) : $id;
			$specObj  = new IModel('spec');
			$specObj->del("id in ( {$idString} ) and seller_id = ".$this->seller['seller_id']);
			$this->redirect('spec_list');
		}
		else
		{
			$this->redirect('spec_list',false);
			Util::showMessage('请选择要删除的规格');
		}
	}
	//修改排序
	public function ajax_sort()
	{
		$id   = IFilter::act(IReq::get('id'),'int');
		$sort = IFilter::act(IReq::get('sort'),'int');

		$goodsDB = new IModel('goods');
		$goodsDB->setData(array('sort' => $sort));
		$goodsDB->update("id = {$id} and seller_id = ".$this->seller['seller_id']);
	}

	//咨询回复
	public function refer_reply()
	{
		$rid     = IFilter::act(IReq::get('refer_id'),'int');
		$content = IFilter::act(IReq::get('content'),'text');

		if($rid && $content)
		{
			$tb_refer = new IModel('refer');
			$seller_id = $this->seller['seller_id'];//商户id
			$data = array(
				'answer' => $content,
				'reply_time' => ITime::getDateTime(),
				'seller_id' => $seller_id,
				'status' => 1
			);
			$tb_refer->setData($data);
			$tb_refer->update("id=".$rid);
		}
		$this->redirect('refer_list');
	}
	/**
	 * @brief查看订单
	 */
	public function order_show()
	{
		//获得post传来的值
		$order_id = IFilter::act(IReq::get('id'),'int');
		$data = array();
		if($order_id)
		{
			$order_show = new Order_Class();
			$data = $order_show->getOrderShow($order_id,0,$this->seller['seller_id']);
			if($data)
			{
		 		//获取地区
		 		$data['area_addr'] = join('&nbsp;',area::name($data['province'],$data['city'],$data['area']));
			 	$this->setRenderData($data);
				$this->redirect('order_show',false);
			}
		}
		if(!$data)
		{
			$this->redirect('order_list');
		}
	}
	/**
	 * @brief 发货订单页面
	 */
	public function order_deliver()
	{
		$order_id = IFilter::act(IReq::get('id'),'int');
		$data     = array();

		if($order_id)
		{
			$order_show = new Order_Class();
			$data = $order_show->getOrderShow($order_id);
			if($data)
			{
				$this->setRenderData($data);
				$this->redirect('order_deliver');
			}
		}
		if(!$data)
		{
			IError::show("订单信息不存在",403);
		}
	}
	/**
	 * @brief 发货操作
	 */
	public function order_delivery_doc()
	{
	 	//获得post变量参数
	 	$order_id = IFilter::act(IReq::get('id'),'int');

	 	//发送的商品关联
	 	$sendgoods = IFilter::act(IReq::get('sendgoods'),'int');

	 	if(!$sendgoods)
	 	{
	 		die('请选择要发货的商品');
	 	}

	 	Order_Class::sendDeliveryGoods($order_id,$sendgoods,$this->seller['seller_id'],'seller');
	 	$this->redirect('order_list');
	}
	/**
	 * @brief 订单列表
	 */
	public function order_list()
	{
		$seller_id = $this->seller['seller_id'];
		$searchArray = Util::getUrlParam('search');
		$searchParam = http_build_query($searchArray);
		$condition = Util::orderSearch(IReq::get('search'));
		$where  = "o.seller_id='$seller_id' and o.if_del=0 and o.status not in(3,4)";
		$where .= $condition ? " and ".$condition : "";
		$page   = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;

		$orderHandle = new IQuery('order as o');
		$orderHandle->order  = "o.id desc";
		$orderHandle->where  = $where;
		$orderHandle->pagesize = 8;
		$orderHandle->page	 = $page;
		$this->orderHandle   = $orderHandle;
		$order_info = array();
		$order_info['seller_id'] = $seller_id;
		$order_info['searchParam'] = $searchParam;
		$this->setRenderData($order_info);
		$this->redirect('order_list');
	}

	//订单导出 Excel
	public function order_report()
	{
		$seller_id = $this->seller['seller_id'];
		$condition = Util::orderSearch(IReq::get('search'));

		$where  = "o.seller_id = ".$seller_id." and o.if_del=0 and o.status not in(3,4)";
		$where .= $condition ? " and ".$condition : "";

		//拼接sql
		$orderHandle = new IQuery('order as o');
		$orderHandle->order  = "o.id desc";
		$orderHandle->fields = "o.*,p.name as payment_name";
		$orderHandle->join   = "left join payment as p on p.id = o.pay_type";
		$orderHandle->where  = $where;
		$orderList = $orderHandle->find();

		//构建 Excel table
		$strTable ='<table width="500" border="1">';
		$strTable .= '<tr>';
		$strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="100">日期</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货人</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">电话</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单金额</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">实际支付</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付方式</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付状态</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">发货状态</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品信息</td>';
		$strTable .= '</tr>';

		foreach($orderList as $k=>$val){
			$strTable .= '<tr>';
			$strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_no'].'</td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['create_time'].' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['accept_name'].' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">&nbsp;'.$val['telphone'].'&nbsp;'.$val['mobile'].' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['payable_amount'].' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['real_amount'].' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['payment_name'].' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.Order_Class::getOrderPayStatusText($val).' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.Order_Class::getOrderDistributionStatusText($val).' </td>';

			$orderGoods = Order_class::getOrderGoods($val['id']);

			$strGoods="";
			foreach($orderGoods as $good){
				$strGoods .= "商品编号：".$good['goodsno']." 商品名称：".$good['name'];
				if ($good['value']!='') $strGoods .= " 规格：".$good['value'];
				$strGoods .= "<br />";
			}
			unset($orderGoods);

			$strTable .= '<td style="text-align:left;font-size:12px;">'.$strGoods.' </td>';
			$strTable .= '</tr>';
		}
		$strTable .='</table>';
		//输出成EXcel格式文件并下载
		$reportObj = new report();
		$reportObj->setFileName('order');
		$reportObj->toDownload($strTable);
		exit();
	}

	//修改商户信息
	public function seller_edit()
	{
		$seller_id = $this->seller['seller_id'];
		$sellerDB        = new IModel('seller');
		$sellerRow = $sellerDB->getObj('id = '.$seller_id);
		$sellerRow['paper_img'] = explode(',',$sellerRow['paper_img']);
		$this->sellerRow = $sellerRow;
		$this->redirect('seller_edit');
	}

	/**
	 * @brief 商户的增加动作
	 */
	public function seller_add()
	{
		$seller_id   = $this->seller['seller_id'];
		$email       = IFilter::act(IReq::get('email'));
		$password    = IFilter::act(IReq::get('password'));
		$repassword  = IFilter::act(IReq::get('repassword'));
		$phone       = IFilter::act(IReq::get('phone'));
		$mobile      = IFilter::act(IReq::get('mobile'));
		$province    = IFilter::act(IReq::get('province'),'int');
		$city        = IFilter::act(IReq::get('city'),'int');
		$area        = IFilter::act(IReq::get('area'),'int');
		$address     = IFilter::act(IReq::get('address'));
		$account     = IFilter::act(IReq::get('account'));
		$server_num  = IFilter::act(IReq::get('server_num'));
		$home_url    = IFilter::act(IReq::get('home_url'));
		$tax         = IFilter::act(IReq::get('tax'),'float');

		if(!$seller_id && $password == '')
		{
			$errorMsg = '请输入密码！';
		}

		if($password != $repassword)
		{
			$errorMsg = '两次输入的密码不一致！';
		}

		//acount 处理
		if(3 != count(explode(",", $account))){
			 $errorMsg = '收款账号错误';
		}

		//操作失败表单回填
		if(isset($errorMsg))
		{
			$this->sellerRow = $_POST;
			$this->redirect('seller_edit',false);
			Util::showMessage($errorMsg);
		}

		//待更新的数据
		$sellerRow = array(
			'account'   => $account,
			'phone'     => $phone,
			'mobile'    => $mobile,
			'email'     => $email,
			'address'   => $address,
			'province'  => $province,
			'city'      => $city,
			'area'      => $area,
			'server_num'=> $server_num,
			'home_url'  => $home_url,
			'tax'      => $tax,
		);

		//创建商家操作类
		$sellerDB   = new IModel("seller");

		//修改密码
		if($password)
		{
			$sellerRow['password'] = md5($password);
		}

		$sellerDB->setData($sellerRow);
		$sellerDB->update("id = ".$seller_id);

		$this->redirect('seller_edit');
	}

	function shop_add(){

		$seller_id   = $this->seller['seller_id'];
		$shop_name    = IFilter::act(IReq::get('shop_name','post'));
		$shop_domain    = IFilter::act(IReq::get('shop_domain','post'));
		$slide_img  = IFilter::act(IReq::get('slide_img','post'));
		$slide_name  = IFilter::act(IReq::get('slide_name','post'));
		$slide_url = IFilter::act(IReq::get('slide_url','post'));
		$server_num = IFilter::act(IReq::get('server_nums','post'));
		if($server_num){
			$server_num = rtrim(implode(',', $server_num),',');
		}
		//待更新的数据
		$sellerRow = array(
			'shop_name'   => $shop_name,
			'shop_domain' => $shop_domain,
			'slide'=>'',
			'server_num'=>$server_num, 
		);
		//banner图片处理
		$slide = '';
		if($slide_img&&$slide_url&&$slide_name){
			foreach ($slide_img as $key => $value) {
				$slide[] = array(
					'name'=>$slide_name[$key],
					'img'=>$slide_img[$key],
					'url'=>$slide_url[$key]);
			}
		} 
		$sellerRow['slide'] = $slide?serialize($slide):$slide;
		//处理上传logo
		if(isset($_FILES['logo']) && $_FILES['logo']['name'] != '')
		{
			$uploadObj = new PhotoUpload();
			$photoInfo = $uploadObj->run();
			$sellerRow['logo'] = $photoInfo['logo']['img'];
		}
		//创建商家操作类
		$sellerDB   = new IModel("seller");
		$sellerDB->setData($sellerRow);
		$sellerDB->update("id = ".$seller_id);
		$this->redirect('shop');
	}
	//[会员列表][单页]
	function camera_user_list()
	{
	 	$page   = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$mb   = IReq::get('mb') ? " and member_mobile <>'' ":'' ;
		$orderHandle = new IQuery('camera_user as o');
		$orderHandle->page = $page;
		$orderHandle->order  = "o.id desc";
		$orderHandle->pagesize = 10;
		$orderHandle->where  = 'seller_id ='.$this->seller['seller_id'].$mb;
		$this->seller_messageHandle  =  $orderHandle;
		$this->redirect('camera_user_list');
	}


	//[团购]添加修改[单页]
	function regiment_edit()
	{
		$id = IFilter::act(IReq::get('id'),'int');

		if($id)
		{
			$regimentObj = new IModel('regiment');
			$where       = 'id = '.$id.' and seller_id = '.$this->seller['seller_id'];
			$regimentRow = $regimentObj->getObj($where);
			if(!$regimentRow)
			{
				$this->redirect('regiment_list');
			}

			//促销商品
			$goodsObj = new IModel('goods');
			$goodsRow = $goodsObj->getObj('id = '.$regimentRow['goods_id']);

			$result = array(
				'isError' => false,
				'data'    => $goodsRow,
			);
			$regimentRow['goodsRow'] = JSON::encode($result);
			$this->regimentRow = $regimentRow;
		}
		$this->redirect('regiment_edit');
	}

	//[团购]删除
	function regiment_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$regObj = new IModel('regiment');
			if(is_array($id))
			{
				$id    = join(',',$id);
			}
			$where = ' id in ('.$id.') and seller_id = '.$this->seller['seller_id'];
			$regObj->del($where);
			$this->redirect('regiment_list');
		}
		else
		{
			$this->redirect('regiment_list',false);
			Util::showMessage('请选择要删除的id值');
		}
	}

	//[团购]添加修改[动作]
	function regiment_edit_act()
	{
		$id      = IFilter::act(IReq::get('id'),'int');
		$goodsId = IFilter::act(IReq::get('goods_id'),'int');

		$dataArray = array(
			'id'        	=> $id,
			'title'     	=> IFilter::act(IReq::get('title','post')),
			'start_time'	=> IFilter::act(IReq::get('start_time','post')),
			'end_time'  	=> IFilter::act(IReq::get('end_time','post')),
			'is_close'      => 1,
			'intro'     	=> IFilter::act(IReq::get('intro','post')),
			'goods_id'      => $goodsId,
			'store_nums'    => IFilter::act(IReq::get('store_nums','post')),
			'limit_min_count' => IFilter::act(IReq::get('limit_min_count','post'),'int'),
			'limit_max_count' => IFilter::act(IReq::get('limit_max_count','post'),'int'),
			'regiment_price'=> IFilter::act(IReq::get('regiment_price','post')),
			'seller_id'     => $this->seller['seller_id'],
		);
		$dataArray['limit_min_count'] = $dataArray['limit_min_count'] <= 0 ? 1 : $dataArray['limit_min_count'];
		$dataArray['limit_max_count'] = $dataArray['limit_max_count'] <= 0 ? $dataArray['store_nums'] : $dataArray['limit_max_count'];
		if($goodsId)
		{
			$goodsObj = new IModel('goods');
			$where    = 'id = '.$goodsId.' and seller_id = '.$this->seller['seller_id'];
			$goodsRow = $goodsObj->getObj($where);

			//商品信息不存在
			if(!$goodsRow)
			{
				$this->regimentRow = $dataArray;
				$this->redirect('regiment_edit',false);
				Util::showMessage('请选择商户自己的商品');
			}

			//处理上传图片
			if(isset($_FILES['img']['name']) && $_FILES['img']['name'] != '')
			{
				$uploadObj = new PhotoUpload();
				$photoInfo = $uploadObj->run();
				$dataArray['img'] = $photoInfo['img']['img'];
			}
			else
			{
				$dataArray['img'] = $goodsRow['img'];
			}

			$dataArray['sell_price'] = $goodsRow['sell_price'];
		}
		else
		{
			$this->regimentRow = $dataArray;
			$this->redirect('regiment_edit',false);
			Util::showMessage('请选择要关联的商品');
		}

		$regimentObj = new IModel('regiment');
		$regimentObj->setData($dataArray);

		if($id)
		{
			$where = 'id = '.$id.' and seller_id = '.$this->seller['seller_id'];
			$regimentObj->update($where);
		}
		else
		{
			$regimentObj->add();
		}
		$this->redirect('regiment_list');
	}

	//结算单修改
	public function bill_edit()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		$billRow = array();

		if($id)
		{
			$billDB  = new IModel('bill');
			$billRow = $billDB->getObj('id = '.$id.' and seller_id = '.$this->seller['seller_id']);
		}

		$this->billRow = $billRow;
		$this->redirect('bill_edit');
	}

	//结算单删除
	public function bill_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');

		if($id)
		{
			$billDB = new IModel('bill');
			$billDB->del('id = '.$id.' and seller_id = '.$this->seller['seller_id'].' and is_pay = 0');
		}

		$this->redirect('bill_list');
	}


	public function bill_status(){
		$id        = IFilter::act(IReq::get('id'),'int');
		$is_pay    = IFilter::act(IReq::get('is_pay'),'int');
		$seller_id = $this->seller['seller_id'];

		$goodsDB = new IModel('bill');
		$goodsDB->setData(array('is_pay' => $is_pay));

	    if($id)
		{
			if(is_array($id))
			{
				foreach($id as $key => $val)
				{
					$goodsDB->update("id = ".$val." and seller_id = ".$seller_id." and is_pay=-1");
				}
			}
			else
			{
				$goodsDB->update("id = ".$val." and seller_id = ".$seller_id." and is_pay=-1");
			}
		}
		$this->redirect("bill_list");
	}

	public function bill_list(){
		$page   = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$is_pay   =  IFilter::act(IReq::get('is_pay'));
		$apply_time   =  IFilter::act(IReq::get('apply_time'));
		$where = 'seller_id='.$this->seller['seller_id'];
		//时间换算
		if(1==$apply_time){
			$lastTime     = ITime::getDateTime('Y-m-d',strtotime("-1 month"));
			$where .= " and apply_time>{$lastTime}";

		}elseif (2==$apply_time) {
			$lastTime     = ITime::getDateTime('Y-m-d',strtotime("-3 month"));
			$where .= " and apply_time>{$lastTime}";
		}elseif (3==$apply_time) {
			$lastTime     = ITime::getDateTime('Y-m-d',strtotime("-3 month"));
			$where .= " and apply_time<{$lastTime}";
		}
		if(''!=$is_pay){
			$where .= " and is_pay={$is_pay}";
		}
		$billHandle = new IQuery('bill');
		$billHandle->page = $page;
		$billHandle->where = $where;
		$this->billHandle= $billHandle;
		$this->redirect('bill_list');
	}

	//结算单更新
	public function bill_update()
	{
		$id            = IFilter::act(IReq::get('id'),'int');
		$start_time    = IFilter::act(IReq::get('start_time'));
		$end_time      = IFilter::act(IReq::get('end_time'));
		$apply_content = IFilter::act(IReq::get('apply_content'));

		$billDB = new IModel('bill');

		if($id)
		{
			$billRow = $billDB->getObj('id = '.$id);
			if($billRow['is_pay'] <= 0)
			{
				$billDB->setData(array('apply_content' => $apply_content));
				$billDB->update('id = '.$id.' and seller_id = '.$this->seller['seller_id']);
			}
		}
		else
		{
			//判断是否存在未处理的申请
			$isSubmitBill = $billDB->getObj(" seller_id = ".$this->seller['seller_id']." and is_pay = 0");
			if($isSubmitBill)
			{
				$this->redirect('bill_list',false);
				Util::showMessage('请耐心等待管理员结算后才能再次提交申请');
			}

			//获取未结算的订单
			$queryObject = CountSum::getSellerGoodsFeeQuery($this->seller['seller_id'],$start_time,$end_time,0);
			$countData   = CountSum::countSellerOrderFee($queryObject->find());

			if($countData['countFee'] > 0)
			{
				$countData['start_time'] = $start_time;
				$countData['end_time']   = $end_time;

				$billString = AccountLog::sellerBillTemplate($countData);
				$data = array(
					'seller_id'  => $this->seller['seller_id'],
					'apply_time' => ITime::getDateTime(),
					'apply_content' => IFilter::act(IReq::get('apply_content')),
					'start_time' => $start_time,
					'end_time' => $end_time,
					'log' => $billString,
					'order_ids' => join(",",$countData['order_ids']),
				);
				$billDB->setData($data);
				$billDB->add();
			}
			else
			{
				$this->redirect('bill_list',false);
				Util::showMessage('当前时间段内没有任何结算货款');
			}
		}
		$this->redirect('bill_list');
	}

	//计算应该结算的货款明细
	public function countGoodsFee()
	{
		$seller_id   = $this->seller['seller_id'];
		$start_time  = IFilter::act(IReq::get('start_time'));
		$end_time    = IFilter::act(IReq::get('end_time'));

		$queryObject = CountSum::getSellerGoodsFeeQuery($seller_id,$start_time,$end_time,0);
		$countData   = CountSum::countSellerOrderFee($queryObject->find());

		if($countData['countFee'] > 0)
		{
			$countData['start_time'] = $start_time;
			$countData['end_time']   = $end_time;

			$billString = AccountLog::sellerBillTemplate($countData);
			$result     = array('result' => 'success','data' => $billString);
		}
		else
		{
			$result = array('result' => 'fail','data' => '当前没有任何款项可以结算');
		}

		die(JSON::encode($result));
	}

	/**
	 * @brief 显示评论信息
	 */
	function comment_edit()
	{
		$cid = IFilter::act(IReq::get('cid'),'int');

		if(!$cid)
		{
			$this->comment_list();
			return false;
		}
		$query = new IQuery("comment as c");
		$query->join = "left join goods as goods on c.goods_id = goods.id left join user as u on c.user_id = u.id";
		$query->fields = "c.*,u.username,goods.name,goods.seller_id";
		$query->where = "c.id=".$cid." and goods.seller_id = ".$this->seller['seller_id'];
		$items = $query->find();

		if($items)
		{
			$this->comment = current($items);
			$this->redirect('comment_edit');
		}
		else
		{
			$this->comment_list();
			$msg = '没有找到相关记录！';
			Util::showMessage($msg);
		}
	}

	/**
	 * @brief 回复评论
	 */
	function comment_update()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		$recontent = IFilter::act(IReq::get('recontents'));
		if($id)
		{
			$commentDB = new IQuery('comment as c');
			$commentDB->join = 'left join goods as go on go.id = c.goods_id';
			$commentDB->where= 'c.id = '.$id.' and go.seller_id = '.$this->seller['seller_id'];
			$checkList = $commentDB->find();
			if(!$checkList)
			{
				IError::show(403,'该商品不属于您，无法对其评论进行回复');
			}

			$updateData = array(
				'recontents' => $recontent,
				'recomment_time' => ITime::getDateTime(),
			);
			$commentDB = new IModel('comment');
			$commentDB->setData($updateData);
			$commentDB->update('id = '.$id);
		}
		$this->redirect('comment_list');
	}

	//商品退款详情
	function refundment_show()
	{
	 	//获得post传来的退款单id值
	 	$refundment_id = IFilter::act(IReq::get('id'),'int');
	 	$data = array();
	 	if($refundment_id)
	 	{
	 		$tb_refundment = new IQuery('refundment_doc as c');
	 		$tb_refundment->join=' left join order as o on c.order_id=o.id left join user as u on u.id = c.user_id';
	 		$tb_refundment->fields = 'u.username,c.*,o.*,c.id as id,c.pay_status as pay_status';
	 		$tb_refundment->where = 'c.id='.$refundment_id.' and c.seller_id = '.$this->seller['seller_id'];
	 		$refundment_info = $tb_refundment->find();
	 		if($refundment_info)
	 		{
	 			$data = current($refundment_info);
	 			$this->data = $data;
	 			$this->setRenderData($data);
	 			$this->redirect('refundment_show',false);
	 		}
	 	}

	 	if(!$data)
		{
			$this->redirect('refundment_list');
		}
	}

	//商品退款操作
	function refundment_update()
	{
		$id           = IFilter::act(IReq::get('id'),'int');
		$pay_status   = IFilter::act(IReq::get('pay_status'),'int');
		$dispose_idea = IFilter::act(IReq::get('dispose_idea'));
		$amount       = IFilter::act(IReq::get('amount'),'float');

		if(!$pay_status)
		{
			die('选择处理状态');
		}

		//商户处理退款
		if($id && Order_Class::isSellerRefund($id,$this->seller['seller_id']) == 2)
		{
			$updateData = array(
				'dispose_time' => ITime::getDateTime(),
				'dispose_idea' => $dispose_idea,
				'pay_status'   => $pay_status,
				'amount'       => $amount,
			);
			$tb_refundment_doc = new IModel('refundment_doc');
			$tb_refundment_doc->setData($updateData);
			$tb_refundment_doc->update('id = '.$id);

			if($pay_status == 2)
			{
				$result = Order_Class::refund($id,$this->seller['seller_id'],'seller');
				if(is_string($result))
				{
					$tb_refundment_doc->rollback();
					die($result);
				}
			}
		}
		$this->redirect('refundment_list');
	}

	//商品复制
	function goods_copy()
	{
		$idArray = explode(',',IReq::get('id'));
		$idArray = IFilter::act($idArray,'int');

		$goodsDB     = new IModel('goods');
		$goodsAttrDB = new IModel('goods_attribute');
		$goodsPhotoRelationDB = new IModel('goods_photo_relation');
		$productsDB = new IModel('products');

		$goodsData = $goodsDB->query('id in ('.join(',',$idArray).') and is_share = 1 and is_del = 0 and seller_id = 0','*');
		if($goodsData)
		{
			foreach($goodsData as $key => $val)
			{
				//判断是否重复
				if( $goodsDB->getObj('seller_id = '.$this->seller['seller_id'].' and name = "'.$val['name'].'"') )
				{
					die('商品不能重复复制');
				}

				$oldId = $val['id'];

				//商品数据
				unset($val['id'],$val['visit'],$val['favorite'],$val['sort'],$val['comments'],$val['sale'],$val['grade'],$val['is_share']);
				$val['seller_id'] = $this->seller['seller_id'];
				$val['goods_no'] .= '-'.$this->seller['seller_id'];
				$val['name']      = IFilter::act($val['name'],'text');
				$val['content']   = IFilter::act($val['content'],'text');

				$goodsDB->setData($val);
				$goods_id = $goodsDB->add();

				//商品属性
				$attrData = $goodsAttrDB->query('goods_id = '.$oldId);
				if($attrData)
				{
					foreach($attrData as $k => $v)
					{
						unset($v['id']);
						$v['goods_id'] = $goods_id;
						$goodsAttrDB->setData($v);
						$goodsAttrDB->add();
					}
				}

				//商品图片
				$photoData = $goodsPhotoRelationDB->query('goods_id = '.$oldId);
				if($photoData)
				{
					foreach($photoData as $k => $v)
					{
						unset($v['id']);
						$v['goods_id'] = $goods_id;
						$goodsPhotoRelationDB->setData($v);
						$goodsPhotoRelationDB->add();
					}
				}

				//货品
				$productsData = $productsDB->query('goods_id = '.$oldId);
				if($productsData)
				{
					foreach($productsData as $k => $v)
					{
						unset($v['id']);
						$v['products_no'].= '-'.$this->seller['seller_id'];
						$v['goods_id']    = $goods_id;
						$productsDB->setData($v);
						$productsDB->add();
					}
				}
			}
			die('success');
		}
		else
		{
			die('复制的商品不存在');
		}
	}

	/**
	 * @brief 添加/修改发货信息
	 */
	public function ship_info_edit()
	{
		// 获取POST数据
    	$id = IFilter::act(IReq::get("sid"),'int');
    	if($id)
    	{
    		$tb_ship   = new IModel("merch_ship_info");
    		$ship_info = $tb_ship->getObj("id=".$id." and seller_id = ".$this->seller['seller_id']);
    		if($ship_info)
    		{
    			$this->data = $ship_info;
    		}
    		else
    		{
    			die('数据不存在');
    		}
    	}
    	$this->setRenderData($this->data);
		$this->redirect('ship_info_edit');
	}
	/**
	 * @brief 设置发货信息的默认值
	 */
	public function ship_info_default()
	{
		$id = IFilter::act( IReq::get('id'),'int' );
        $default = IFilter::string(IReq::get('default'));
        $tb_merch_ship_info = new IModel('merch_ship_info');
        if($default == 1)
        {
            $tb_merch_ship_info->setData(array('is_default'=>0));
            $tb_merch_ship_info->update("seller_id = ".$this->seller['seller_id']);
        }
        $tb_merch_ship_info->setData(array('is_default' => $default));
        $tb_merch_ship_info->update("id = ".$id." and seller_id = ".$this->seller['seller_id']);
        $this->redirect('ship_info_list');
	}
	/**
	 * @brief 保存添加/修改发货信息
	 */
	public function ship_info_update()
	{
		// 获取POST数据
    	$id = IFilter::act(IReq::get('sid'),'int');
    	$ship_name = IFilter::act(IReq::get('ship_name'));
    	$ship_user_name = IFilter::act(IReq::get('ship_user_name'));
    	$sex = IFilter::act(IReq::get('sex'),'int');
    	$province =IFilter::act(IReq::get('province'),'int');
    	$city = IFilter::act(IReq::get('city'),'int');
    	$area = IFilter::act(IReq::get('area'),'int');
    	$address = IFilter::act(IReq::get('address'));
    	$postcode = IFilter::act(IReq::get('postcode'),'int');
    	$mobile = IFilter::act(IReq::get('mobile'));
    	$telphone = IFilter::act(IReq::get('telphone'));
    	$is_default = IFilter::act(IReq::get('is_default'),'int');

    	$tb_merch_ship_info = new IModel('merch_ship_info');

    	//判断是否已经有了一个默认地址
    	if(isset($is_default) && $is_default==1)
    	{
    		$tb_merch_ship_info->setData(array('is_default' => 0));
    		$tb_merch_ship_info->update('seller_id = 0');
    	}
    	//设置存储数据
    	$arr['ship_name'] = $ship_name;
	    $arr['ship_user_name'] = $ship_user_name;
	    $arr['sex'] = $sex;
    	$arr['province'] = $province;
    	$arr['city'] =$city;
    	$arr['area'] =$area;
    	$arr['address'] = $address;
    	$arr['postcode'] = $postcode;
    	$arr['mobile'] = $mobile;
    	$arr['telphone'] =$telphone;
    	$arr['is_default'] = $is_default;
    	$arr['is_del'] = 1;
    	$arr['seller_id'] = $this->seller['seller_id'];

    	$tb_merch_ship_info->setData($arr);
    	//判断是添加还是修改
    	if($id)
    	{
    		$tb_merch_ship_info->update('id='.$id." and seller_id = ".$this->seller['seller_id']);
    	}
    	else
    	{
    		$tb_merch_ship_info->add();
    	}
		$this->redirect('ship_info_list');
	}
	/**
	 * @brief 删除发货信息到回收站中
	 */
	public function ship_info_del()
	{
		// 获取POST数据
    	$id = IFilter::act(IReq::get('id'),'int');
		//加载 商家发货点信息
    	$tb_merch_ship_info = new IModel('merch_ship_info');
		if($id)
		{
			$tb_merch_ship_info->del(Util::joinStr($id)." and seller_id = ".$this->seller['seller_id']);
			$this->redirect('ship_info_list');
		}
		else
		{
			$this->redirect('ship_info_list',false);
			Util::showMessage('请选择要删除的数据');
		}
	}

	/**
	 * @brief 配送方式修改
	 */
    public function delivery_edit()
	{
		$data = array();
        $delivery_id = IFilter::act(IReq::get('id'),'int');

        if($delivery_id)
        {
            $delivery = new IModel('delivery_extend');
            $data = $delivery->getObj('delivery_id = '.$delivery_id.' and seller_id = '.$this->seller['seller_id']);
		}
		else
		{
			die('配送方式');
		}

		//获取省份
		$areaData = array();
		$areaDB = new IModel('areas');
		$areaList = $areaDB->query('parent_id = 0');
		foreach($areaList as $val)
		{
			$areaData[$val['area_id']] = $val['area_name'];
		}
		$this->areaList  = $areaList;
		$this->data_info = $data;
		$this->area      = $areaData;
        $this->redirect('delivery_edit');
	}

	/**
	 * 配送方式修改
	 */
    public function delivery_update()
    {
        //首重重量
        $first_weight = IFilter::act(IReq::get('first_weight'),'float');
        //续重重量
        $second_weight = IFilter::act(IReq::get('second_weight'),'float');
        //首重价格
        $first_price = IFilter::act(IReq::get('first_price'),'float');
        //续重价格
        $second_price = IFilter::act(IReq::get('second_price'),'float');
        //是否支持物流保价
        $is_save_price = IFilter::act(IReq::get('is_save_price'),'int');
        //地区费用类型
        $price_type = IFilter::act(IReq::get('price_type'),'int');
        //启用默认费用
        $open_default = IFilter::act(IReq::get('open_default'),'int');
        //支持的配送地区ID
        $area_groupid = serialize(IReq::get('area_groupid'));
        //配送地址对应的首重价格
        $firstprice = serialize(IReq::get('firstprice'));
        //配送地区对应的续重价格
        $secondprice = serialize(IReq::get('secondprice'));
        //保价费率
        $save_rate = IFilter::act(IReq::get('save_rate'),'float');
        //最低保价
        $low_price = IFilter::act(IReq::get('low_price'),'float');
		//配送ID
        $delivery_id = IFilter::act(IReq::get('deliveryId'),'int');

        $deliveryDB  = new IModel('delivery');
        $deliveryRow = $deliveryDB->getObj('id = '.$delivery_id);
        if(!$deliveryRow)
        {
        	die('配送方式不存在');
        }

        //如果选择指定地区配送就必须要选择地区
        if($price_type == 1 && !$area_groupid)
        {
			die('请设置配送地区');
        }

        $data = array(
        	'first_weight' => $first_weight,
        	'second_weight'=> $second_weight,
        	'first_price'  => $first_price,
        	'second_price' => $second_price,
        	'is_save_price'=> $is_save_price,
        	'price_type'   => $price_type,
        	'open_default' => $open_default,
        	'area_groupid' => $area_groupid,
        	'firstprice'   => $firstprice,
        	'secondprice'  => $secondprice,
        	'save_rate'    => $save_rate,
        	'low_price'    => $low_price,
        	'seller_id'    => $this->seller['seller_id'],
        	'delivery_id'  => $delivery_id,
        );
        $deliveryExtendDB = new IModel('delivery_extend');
        $deliveryExtendDB->setData($data);
        $deliveryObj = $deliveryExtendDB->getObj("delivery_id = ".$delivery_id." and seller_id = ".$this->seller['seller_id']);
        //已经存在了
        if($deliveryObj)
        {
        	$deliveryExtendDB->update('delivery_id = '.$delivery_id.' and seller_id = '.$this->seller['seller_id']);
        }
        else
        {
        	$deliveryExtendDB->add();
        }
		$this->redirect('delivery');
    }

	//[促销活动] 添加修改 [单页]
	function pro_rule_edit()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$promotionObj = new IModel('promotion');
			$where = 'id = '.$id.' and seller_id='.$this->seller['seller_id'];
			$this->promotionRow = $promotionObj->getObj($where);
		}
		$this->redirect('pro_rule_edit');
	}

	//[促销活动] 添加修改 [动作]
	function pro_rule_edit_act()
	{
		$id           = IFilter::act(IReq::get('id'),'int');
		$user_group   = IFilter::act(IReq::get('user_group','post'));
		$promotionObj = new IModel('promotion');
		if(is_string($user_group))
		{
			$user_group_str = $user_group;
		}
		else
		{
			$user_group_str = ",".join(',',$user_group).",";
		}

		$dataArray = array(
			'name'       => IFilter::act(IReq::get('name','post')),
			'condition'  => IFilter::act(IReq::get('condition','post')),
			'is_close'   => IFilter::act(IReq::get('is_close','post')),
			'start_time' => IFilter::act(IReq::get('start_time','post')),
			'end_time'   => IFilter::act(IReq::get('end_time','post')),
			'intro'      => IFilter::act(IReq::get('intro','post')),
			'award_type' => IFilter::act(IReq::get('award_type','post')),
			'type'       => 0,
			'user_group' => $user_group_str,
			'award_value'=> IFilter::act(IReq::get('award_value','post')),
			'seller_id'  => $this->seller['seller_id'],
		);

		if(!in_array($dataArray['award_type'],array(1,2,6)))
		{
			IError::show('促销类型不符合规范',403);
		}

		$promotionObj->setData($dataArray);

		if($id)
		{
			$where = 'id = '.$id;
			$promotionObj->update($where);
		}
		else
		{
			$promotionObj->add();
		}
		$this->redirect('pro_rule_list');
	}

	//[促销活动] 删除
	function pro_rule_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if(!empty($id))
		{
			$promotionObj = new IModel('promotion');
			if(is_array($id))
			{
				$idStr = join(',',$id);
				$where = ' id in ('.$idStr.')';
			}
			else
			{
				$where = 'id = '.$id;
			}
			$promotionObj->del($where.' and seller_id = '.$this->seller['seller_id']);
			$this->redirect('pro_rule_list');
		}
		else
		{
			$this->redirect('pro_rule_list',false);
			Util::showMessage('请选择要删除的促销活动');
		}
	}

	//修改订单价格
	public function order_discount()
	{
		$order_id = IFilter::act(IReq::get('order_id'),'int');
		$discount = IFilter::act(IReq::get('discount'),'float');
		$orderDB  = new IModel('order');
		$orderRow = $orderDB->getObj('id = '.$order_id.' and status = 1 and distribution_status = 0 and seller_id = '.$this->seller['seller_id']);
		if($orderRow)
		{
			//还原价格
			$newOrderAmount = ($orderRow['order_amount'] - $orderRow['discount']) + $discount;
			$orderDB->setData(array('discount' => $discount,'order_amount' => $newOrderAmount));
			if($orderDB->update('id = '.$order_id))
			{
				die(JSON::encode(array('result' => true,'orderAmount' => $newOrderAmount)));
			}
		}
		die(JSON::encode(array('result' => false)));
	}

	// 消息通知
	public function message_list()
	{
		$page   = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$seller_messObject = new seller_mess($this->seller['seller_id']);
		$msgIds = $seller_messObject->getAllMsgIds();
		$msgIds = empty($msgIds) ? 0 : $msgIds;
		$needReadNum = $seller_messObject->needReadNum();

		$seller_messageHandle = new IQuery('seller_message');
		$seller_messageHandle->where = "id in(".$msgIds.")";
		$seller_messageHandle->order= "id desc";
		$seller_messageHandle->page = $page;

		$this->needReadNum = $needReadNum;
		$this->seller_messObject = $seller_messObject;
		$this->seller_messageHandle = $seller_messageHandle;

		$this->redirect("message_list");
	}

	// 消息详情
	public function message_show()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if (!empty($id))
		{
			$seller_messObject = new seller_mess($this->seller['seller_id']);
			$seller_messObject->writeMessage($id, 1);
		}
		$this->redirect('message_show');
	}

	// 消息删除
	public function message_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if (!empty($id))
		{
			$seller_messObject = new seller_mess($this->seller['seller_id']);
			if (is_array($id)) {
				foreach ($id as $val)
				{
					$seller_messObject->delMessage($val);
				}
			}else {
				$seller_messObject->delMessage($id);
			}
		}
		$this->redirect('message_list');
	}

	//订单备注
	public function order_note()
	{
	 	//获得post数据
	 	$order_id = IFilter::act(IReq::get('order_id'),'int');
	 	$note = IFilter::act(IReq::get('note'),'text');

	 	//获得order的表对象
	 	$tb_order =  new IModel('order');
	 	$tb_order->setData(array(
	 		'note'=>$note
	 	));
	 	$tb_order->update('id = '.$order_id.' and seller_id = '.$this->seller['seller_id']);
	 	$this->redirect("/seller/order_show/id/".$order_id,true);
	}

	/**
	 * @brief 删除咨询信息
	 */
	function refer_del()
	{
		$refer_ids = IFilter::act(IReq::get('id'),'int');
		$refer_ids = is_array($refer_ids) ? $refer_ids : array($refer_ids);
		if($refer_ids)
		{
			$ids = join(',',$refer_ids);
			if($ids)
			{
				//查询咨询的商品是否属于当前商户
				$referDB        = new IQuery('refer as re,goods as go');
				$referDB->where = "re.id in (".$ids.") and re.goods_id = go.id and go.seller_id = ".$this->seller['seller_id'];
				$referDB->fields= "re.id";
				$referGoods     = $referDB->find();
				$referModel     = new IModel('refer');
				foreach($referGoods as $reId)
				{
					$referModel->del($reId['id']);
				}
			}
		}
		$this->redirect('refer_list');
	}

	public function shop(){
		$seller_id = $this->seller['seller_id'];
		$sellerDB        = new IModel('seller');
		$sellerRow = $sellerDB->getObj('id = '.$seller_id);
		//selde
		$sellerRow['slide'] = $sellerRow['slide']?unserialize($sellerRow['slide']):array();
		//server_num
		$sellerRow['server_num'] = $sellerRow['server_num']?explode(',', $sellerRow['server_num']):array();
		$this->setRenderData($sellerRow);
		$this->sellerRow = $sellerRow;
		$this->redirect('shop');
	}


	public function banner_upload(){
		$result = array(
			'isError' => true,
		);
		if(isset($_FILES['attach']['name']) && $_FILES['attach']['name'] != '')
		{
			$photoObj = new PhotoUpload();
			$photo    = $photoObj->run();

			if($photo['attach']['img'])
			{
				$result['img'] = $photo['attach']['img'];
				$result['name'] =   IFilter::act(IReq::get('name','post'));
				$result['url'] = IFilter::act(IReq::get('url','post'));
				$result['isError'] = false;
			}
			else
			{
				$result['message'] = '上传失败';
			}
		}
		else
		{
			$result['message'] = '请选择图片';
		}

       echo '<script type="text/javascript">parent.callback_banner('.JSON::encode($result).');</script>';
	}

	/*********************************** 订单模块 begin*******************************/
	/**
	 * @brief 退款单页面
	 */
	public function order_refundment()
	{
		//去掉左侧菜单和上部导航
		$this->layout='';
		$orderId   = IFilter::act(IReq::get('id'),'int');
		$refundsId = IFilter::act(IReq::get('refunds_id'),'int');

		if($orderId)
		{
			$orderDB = new Order_Class();
			$data    = $orderDB->getOrderShow($orderId);

			//已经存退款申请
			if($refundsId)
			{
				$refundsDB  = new IModel('refundment_doc');
				$refundsRow = $refundsDB->getObj('id = '.$refundsId);
				$data['refunds'] = $refundsRow;
			}
			$this->setRenderData($data);
			$this->data = $data;
			$this->redirect('theme:sysdefault/order/order_refundment');
			return;
		}
		die('订单数据不存在');
	}

	/**
	 * @brief 完成或作废订单页面
	 **/
	public function order_complete()
	{
		//去掉左侧菜单和上部导航
		$this->layout='';
		$order_id = IFilter::act(IReq::get('id'),'int');
		$type     = IFilter::act(IReq::get('type'),'int');
		$order_no = IFilter::act(IReq::get('order_no'));

		//oerder表的对象
		$tb_order = new IModel('order');
		$tb_order->setData(array(
			'status'          => $type,
			'completion_time' => ITime::getDateTime(),
		));
		$tb_order->update('id='.$order_id);

		//生成订单日志
		$tb_order_log = new IModel('order_log');
		$action = '作废';
		$note   = '订单【'.$order_no.'】作废成功';

		if($type=='5')
		{
			$action = '完成';
			$note   = '订单【'.$order_no.'】完成成功';

			//完成订单并且进行支付
			Order_Class::updateOrderStatus($order_no);

			//增加用户评论商品机会
			Order_Class::addGoodsCommentChange($order_id);

			$logObj = new log('db');
			$logObj->write('operation',array("管理员:".ISafe::get('admin_name'),"订单更新为完成",'订单号：'.$order_no));
		}
		else
		{
			Order_class::resetOrderProp($order_id);

			$logObj = new log('db');
			$logObj->write('operation',array("管理员:".ISafe::get('admin_name'),"订单更新为作废",'订单号：'.$order_no));
		}

		$tb_order_log->setData(array(
			'order_id' => $order_id,
			'user'     => $this->admin['admin_name'],
			'action'   => $action,
			'result'   => '成功',
			'note'     => $note,
			'addtime'  => ITime::getDateTime(),
		));
		$tb_order_log->add();
		die('success');
	}
	/**
	 * @brief 支付订单页面collection_doc
	 */
	public function order_collection()
	{
	 	//去掉左侧菜单和上部导航
	 	$this->layout='';
	 	$order_id = IFilter::act(IReq::get('id'),'int');
	 	$data = array();
	 	if($order_id)
	 	{
	 		$order_show = new Order_Class();
	 		$data = $order_show->getOrderShow($order_id);
	 	}
	 	$this->setRenderData($data);
	 	$this->redirect('theme:sysdefault/order/order_collection');

	}
	/*********************************** 订单模块 end*******************************/

	//[广告] 添加修改 (单页)
	function ad_edit()
	{
		$id = IFilter::act( IReq::get('id'),'int' );
		if($id)
		{
			$obj = new IModel('ad_manage');
			$where = 'id = '.$id;
			$this->adRow = $obj->getObj($where);
		}
		$this->redirect('ad_edit',false);
	}

	//[广告] 删除
	function ad_del()
	{
		$id = IFilter::act( IReq::get('id') , 'int' );
		if(!empty($id))
		{
			$obj = new IModel('ad_manage');
			if(is_array($id) && isset($id[0]) && $id[0]!='')
			{
				$id_str = join(',',$id);
				$where = ' id in ('.$id_str.')';
			}
			else
			{
				$where = 'id = '.$id;
			}
			$obj->del($where);
			$this->redirect('ad_list');
		}
		else
		{
			$this->redirect('ad_list',false);
			Util::showMessage('请选择要删除的广告');
		}
	}
	//[广告] 添加和修改动作
	function ad_edit_act()
	{
		$id      = IFilter::act( IReq::get('id'),'int' );
		$content = IReq::get('content');

		//附件上传
		if(isset($_FILES) && $_FILES)
		{
			$upType = isset($_FILES['img']) ? array("gif","png","jpg") : array('flv','swf');
			$upObj  = new IUpload("5000",$upType);
			$dir    = IWeb::$app->config['upload'].'/'.date('Y')."/".date('m')."/".date('d');
			$upObj->setDir($dir);
			$upState = $upObj->execute();
			$result = $upState ? current($upState) : "";
			if($result && isset($result[0]['flag']) && $result[0]['flag'] == 1)
			{
				//最终附件路径
				$content = $dir.'/'.$result[0]['name'];
			}
			else if(!$content)
			{
				IError::show(403,"请上传正确的附件数据");
			}
		}

		$adObj = new IModel('ad_manage');
		$dataArray = array(
			'content'     => IFilter::addSlash($content),
			'name'        => IFilter::act(IReq::get('name')),
			'position_id' => IFilter::act(IReq::get('position_id')),
			'type'        => IFilter::act(IReq::get('type')),
			'link'        => IFilter::addSlash(IReq::get('link')),
			'start_time'  => IFilter::act(IReq::get('start_time')),
			'end_time'    => IFilter::act(IReq::get('end_time')),
			'description' => IFilter::act(IReq::get('description'),'text'),
			'order'       => IFilter::act(IReq::get('order'),'int'),
			'goods_cat_id'=> IFilter::act(IReq::get('goods_cat_id'),'int'),
			'status'=> IFilter::act(IReq::get('status'),'int'),
			'seller_id'=>$this->seller['seller_id'],
		);

		$adObj->setData($dataArray);
		if($id)
		{
			$where = 'id = '.$id;
			$adObj->update($where);
		}
		else
		{
			$adObj->add();
		}
		$this->redirect("ad_list");
	}
}