<?php
/*
 +----------------------------------------------------------------------
 | Copyright (c) 2017  All rights reserved.
 +----------------------------------------------------------------------
 | Author: Andy
 +----------------------------------------------------------------------
 | CreateDate:  18/01/15 下午1:42
 +----------------------------------------------------------------------
*/
namespace app\install\controller;

use think\Controller;

class Index extends Controller {

	protected $status;

	public function _initialize() {

        //验证安装文件
        /*if (is_file(ROOT_PATH . 'data/install.lock') && is_file(APP_PATH . 'database.php')) {
            $this->redirect('/admin');
        }*/

		if ($this->request->action() != 'complete' && is_file(APP_PATH . 'database.php') && is_file(ROOT_PATH . 'data/install.lock')) {
			return $this->redirect('/admin');
		}

        $this->status = [
            'index'    => 'light',
            'check'    => 'light',
            'config'   => 'light',
            'sql'      => 'light',
            'complete' => 'light',
        ];

		$this->assign('product_name',config('product_name'));//产品名
	}

	public function index() {
		$this->status['index'] = 'yellow';
		$this->assign('status', $this->status);
        
        $this->assign('company_name',config('company_name'));//公司名
        $this->assign('company_website_domain',config('company_website_domain'));
        $this->assign('website_domain',config('website_domain'));
		return $this->fetch();
	}

	/**
	 * 检查目录
	 * @return [type] [description]
	 * @date   2017-09-07
	 */
	public function check() {
		if ($this->request->isPost()) {
			if(session('error')){
                $this->error('环境检测没有通过，请调整环境后重试！');
            }else{
                $this->success('恭喜您环境检测通过', url('index/config'));
            }
		} else{
			session('error', false);
			//环境检测
			$env = check_env();

			//目录文件读写检测
			if (IS_WRITE) {
				$dirfile = check_dirfile();
				$this->assign('dirfile', $dirfile);
			}

			//函数检测
			$func = check_func();

			session('step', 1);
			$this->assign('env', $env);
			$this->assign('func', $func);

			$this->status['index'] = 'success';
			$this->status['check'] = 'yellow';
			$this->assign('status', $this->status);
			return $this->fetch();
		}
		
	}

	/**
	 * 配置数据库
	 * @param  [type] $db [description]
	 * @param  [type] $admin [description]
	 * @param  [type] $webconfig [description]
	 * @return [type] [description]
	 * @date   2017-09-07
	 */
	public function config($db = null, $admin = null, $webconfig = null) {
		if ($this->request->isPost()) {

            //检测数据库配置
			$result = $this->validate($db,'InstallConfig.db_config');
            if(true !== $result){
                $this->error($result);
            }

            //检测网站配置信息
            $result = $this->validate($webconfig,'InstallConfig.web_config');
            if(true !== $result){
                $this->error($result);
            }

            $result = $this->validate($admin,'InstallConfig.admin_info');
            if(true !== $result){
                $this->error($result);
            }

			//缓存管理员信息
			$admin_info = [
				'username'   => $admin['admin_username'],
				'password'   => $admin['admin_password'],
				'repassword' => $admin['admin_repassword'],
				'email'      => $admin['admin_email'],
			];
			session('admin_info', $admin_info);
			//缓存管理员信息
			session('web_config', $webconfig);
			//缓存数据库配置
			session('db_config', $db);

			//创建数据库
			$dbname = $db['database'];
			unset($db['database']);
			$db_obj  = \think\Db::connect($db);
			$sql = "CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8";

			if (false === $db_obj->execute($sql)) {
                return $this->error($db_obj->getError());
			} else {
				//$this->success();
                $this->success('开始安装...', url('index/sql'));
			    //$this->redirect('index/sql');
			}
			
		} else {
			$this->status['index']  = 'success';
			$this->status['check']  = 'success';
			$this->status['config'] = 'yellow';
			$this->assign('status', $this->status);
			return $this->fetch();
		}
	}

	/**
	 * 数据库安装
	 * @return [type] [description]
	 */
	public function sql() {
		session('error', false);
		$this->status['index']  = 'success';
		$this->status['check']  = 'success';
		$this->status['config'] = 'success';
		$this->status['sql']    = 'yellow';
		$this->assign('status', $this->status);
		echo $this->fetch();
		if (session('update')) {
			$db = \think\Db::connect();
			//更新数据表
			update_tables($db, config('prefix'));
		} else {
			//连接数据库
			$dbconfig = session('db_config');
			$db       = \think\Db::connect($dbconfig);
			//创建数据表
			create_tables($db, $dbconfig['prefix']);
			//更新网站信息
			update_webconfig($db, $dbconfig['prefix'], session('web_config'));
			//注册创始人帐号
			register_administrator($db, $dbconfig['prefix'], session('admin_info'));

			//创建配置文件
			$conf = write_config($dbconfig);
			session('config_file', $conf);

		}

		if (session('error')) {
			show_msg('失败');
		} else {
			echo '<script type="text/javascript">location.href = "'.url('install/index/complete').'";</script>';
		}

	}

	/**
	 * 完成
	 * @return [type] [description]
	 * @date   2017-09-07
	 */
	public function complete() {
		$this->status['index']    = 'success';
		$this->status['check']    = 'success';
		$this->status['config']   = 'success';
		$this->status['sql']      = 'success';
		$this->status['complete'] = 'yellow';
		$this->assign('status', $this->status);
		$this->assign('status', $this->status);
		return $this->fetch();
	}


}