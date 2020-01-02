<?php
/**
 * Created by PhpStorm.
 * User: 25754
 * Date: 2019/4/27
 * Time: 14:52
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class Customer extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->set_admin_view_dir();
        $this->load->model('customer_model');
    }

    public function get()
    {
        $start = $this->input->post('start');
        $limit = $this->input->post('limit');
        $arr = $this->customer_model->get($start, $limit);
        $count = count($this->customer_model->get());
        echo json_encode(array(
            'err' => 0,
            'data' => $arr,
            'total' => $count
        ));
    }
    public function getMessage()
    {
        $start = $this->input->post('start');
        $limit = $this->input->post('limit');
        $arr = $this->customer_model->getMessage($start, $limit);
        $count = count($this->customer_model->getMessage());
        echo json_encode(array(
            'err' => 0,
            'data' => $arr,
            'total' => $count
        ));
    }

    public function index()
    {
        $htm["layout"] = $this->load->view('customer/index', null, true);
        $this->load->view('frame', $htm);
    }

    public function message()
    {
        $htm["layout"] = $this->load->view('customer/message', null, true);
        $this->load->view('frame', $htm);
    }

    public function add()
    {
        $id = $this->input->get("id");
        $csrf = array(
            'name' => $this->security->get_csrf_token_name(),
            'hash' => $this->security->get_csrf_hash()
        );
        $data['csrf'] = $csrf;
        $info = array();
        $title = "添加会员";
        if ($id) {
            $title = "修改会员";
            //获取数据
            $info = $this->customer_model->edit($id);
        }
        $data['data'] = $info;
        $data['id'] = $id;
        $html = $this->load->view('customer/add', $data, true);
        echo json_encode(array("title" => $title, "html" => $html));
    }

    public function validate()
    {
        $this->load->library('form_validation');
        $this->form_validation->set_rules('post[password]', '会员密码', 'trim|required|min_length[5]', array("required" => "请输入%s", "min_length" => "%s长度最少5位"));
        $this->form_validation->set_rules('post[rpassword]', '会员密码', 'required|trim|matches[post[password]]', array("required" => "请再次输入%s", "matches" => "两次输入%s不一致"));
        if ($this->form_validation->run() == FALSE) {
            $errors = explode("\n", validation_errors());
            die(json_encode(array("code" => -1, "msg" => strip_tags($errors[0]))));
        }
    }

    public function add_op()
    {
        $id = $this->input->post("id");
        $post = $this->input->post("post");
        $this->load->library('form_validation');
        $this->form_validation->set_rules('post[truename]', '会员名称', 'required|trim', array("required" => "请输入%s"));
        $this->form_validation->set_rules('post[name]', '会员号', 'required|trim', array("required" => "请输入%s"));
        if ($this->form_validation->run() == FALSE) {
            $errors = explode("\n", validation_errors());
            die(json_encode(array("code" => -1, "msg" => strip_tags($errors[0]))));
        }
        $time = date("Y-m-d H:i:s", time());
        if ($id) {
            if ($post['password']) {
                //验证密码
                $this->validate();
            } else {
                unset($post['password']);
            }
            unset($post['rpassword']);
            //修改
            $post['id'] = $id;
            $post['update_entered'] = $time;
            $result = $this->customer_model->update($post);
            if ($result) {
                die(json_encode(array("code" => 0, "msg" => "修改会员成功")));
            }
            die(json_encode(array("code" => 1, "msg" => "修改会员失败")));
        } else {
            $this->validate();
            //添加
            unset($post['rpassword']);
            $post['date_entered'] = $post['update_entered'] = $time;
            $num = rand(1, 15);
            $post['head_img'] = "/static/chat/images/head/{$num}.jpg";
            $result = $this->customer_model->insert($post);
            if ($result) {
                die(json_encode(array("code" => 0, "msg" => "添加会员成功")));
            }
            die(json_encode(array("code" => 1, "msg" => "添加会员失败")));
        }
    }

    public function delete()
    {
        $id = $this->input->get("id");
        $result = $this->customer_model->delete($id);
        if ($result) {
            die(json_encode(array("code" => 0, "msg" => "删除会员成功")));
        }
        die(json_encode(array("code" => 1, "msg" => "删除会员失败")));
    }

}