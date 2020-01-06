<?php
/**
 * Created by PhpStorm.
 * User: 25754
 * Date: 2019/8/19
 * Time: 9:04
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class Chat_model extends Common_model
{
    private $_customer = null;
    private $_chat = null;
    private $_chat_msg = null;
    private $_chat_msg_receive = null;

    function __construct()
    {
        parent::__construct();
        $this->_customer = $this->config->item("customer");
        $this->_chat = $this->config->item("chat");
        $this->_chat_msg = $this->config->item("chat_msg");
        $this->_chat_msg_receive = $this->config->item("chat_msg_receive");
    }

    public function login_op($post)
    {
        return $this->db->get_where($this->_customer, array("name" => $post['username'], "password" => $post['password'], "is_deleted" => 0))->row_array();
    }

    public function getSearchFriend($val)
    {
        return $this->db->get_where($this->_customer, "truename like '%{$val}%' or name like '%{$val}%'")->result_array();
    }

    public function getMsgHistory($uid, $chat_id, $start, $limit)
    {
        $keyword = $this->input->get("val");
        //分页条件
        $condition = array($start, $limit);
        $where = "m.chat_id = '$chat_id' and r.is_deleted = 0 and r.to_user_id = $uid";

        if ($keyword) {
            $where .= ' and m.msg like "%'.$keyword.'%"';
        }

        $join[0] = array(
            $this->_chat_msg .' as m',
            'm.id = r.msg_id',
            "left"
        );
        $join[1] = array(
            $this->_customer .' as c',
            'c.id = m.send_user_id',
            "left"
        );
        $select = 'm.*, c.truename, c.head_img';
        $order = '';
        $arr = $this->getAllCommon($this->_chat_msg_receive.' as r', $where, $select, $join, $order, $condition);
        $now_date = date("Y-m-d");
        foreach ($arr as $key => $value) {
            $strtotime = strtotime($value['date_entered']);
            if ($now_date == date("Y-m-d", $strtotime)) {
                $date_entered = date("H:i", $strtotime);
            }else{
                $date_entered = date("y/m/d", $strtotime);
            }
            $arr[$key]['date_entered'] = $date_entered;
        }
        return $arr;
    }
}