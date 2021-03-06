<?php
/**
 * @copyright (c) 2014 aircheng.com
 * @file other.php
 * @brief 其他api方法
 * @author chendeshan
 * @date 2016/4/11 12:54:16
 * @version 4.4
 */
class APIOther
{
	//获取促销规则
	public function getProrule($seller_id = 0)
	{
		$proRuleObj = new ProRule(999999999,$seller_id);
		$proRuleObj->isGiftOnce = false;
		$proRuleObj->isCashOnce = false;
		return $proRuleObj->getInfo();
	}

	//获取支付方式
	public function getPaymentList()
	{
		$user_id = IWeb::$app->getController()->user['user_id'];
		$where = 'status = 0';

		if(!$user_id)
		{
			$where .= " and class_name != 'balance'";
		}

		switch(IClient::getDevice())
		{
			//移动支付
			case IClient::MOBILE:
			{
				//如果是微信客户端,必须用微信专用支付
				if(IClient::isWechat() == true)
				{
					$where .= " and (class_name in ( 'wap_wechat','balance' ) or ( type = 2 and client_type in(2,3) )) ";
				}
				else
				{
					$where .= " and client_type in(2,3) and class_name !=  'wap_wechat' ";
				}
			}
			break;

			//pc支付
			case IClient::PC:
			{
				$where .= ' and client_type in(1,3) ';
			}
			break;
		}
		$paymentDB = new IModel('payment');
		return $paymentDB->query($where,"*","`order` asc");
	}

	//线上充值的支付方式
	public function getPaymentListByOnline()
	{
		$where = " type = 1 and status = 0 and class_name not in ('balance','offline') ";
		switch(IClient::getDevice())
		{
			//移动支付
			case IClient::MOBILE:
			{
				//如果是微信客户端,必须用微信专用支付
				if(IClient::isWechat() == true)
				{
					$where .= " and (class_name in ( 'wap_wechat','balance' ) or ( type = 2 and client_type in(2,3) )) ";
				}
				else
				{
					$where .= " and client_type in(2,3) and class_name !=  'wap_wechat' ";
				}
			}
			break;

			//pc支付
			case IClient::PC:
			{
				$where .= ' and client_type in(1,3) ';
			}
			break;
		}

		$paymentDB = new IModel('payment');
		return $paymentDB->query($where,"*","`order` asc");
	}

	//获取banner数据
	public function getBannerList($seller_id='')
	{	
		if($seller_id){
			$siteConfigObj = new IQuery('seller');
			$siteConfigObj->where = " id=".$seller_id;
			$siteConfigObj->fields = "slide as index_slide";
			$site_config = $siteConfigObj->find();
			if(is_array($site_config))  $site_config = $site_config[0];
		}else{
			$siteConfigObj = new Config("site_config");
			$site_config   = $siteConfigObj->getInfo();
		}

		//读取本地serialize信息
		if(isset($site_config['index_slide']) && $site_config['index_slide'])
		{
			return unserialize($site_config['index_slide']);
		}
		return false;
	}

	public function getCategoryListTop($seller_id=0){
		$query = new IQuery('category');
		$query->where = ' parent_id = 0 and visibility = 1 and seller_id='.$seller_id;
		$query->order = " sort asc";
		$query->limit = 20;
		return $query->find();
	}
}