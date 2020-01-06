<?php
/**
 * Created by PhpStorm.
 * User: 25754
 * Date: 2019/8/19
 * Time: 9:04
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class Customer_model extends Common_model
{
    private $_admin = null;
    private $_customer = null;
    private $_chat_friend = null;
    private $_chat = null;
    private $_chat_msg = null;

    function __construct()
    {
        parent::__construct();
        $this->_customer = $this->config->item("customer");
        $this->_admin = $this->config->item("admin");
        $this->_chat_friend = $this->config->item("chat_friend");
        $this->_chat_msg = $this->config->item("chat_msg");
        $this->_chat = $this->config->item("chat");
    }

    /**
     * 获取聊天信息
     * @param string $start
     * @param string $limit
     * @return mixed
     */
    public function getMessage($start = '', $limit = '')
    {
        $keyword = $this->input->post("keyword");
        //分页条件
        $condition = array($start, $limit);
        $where = '1=1';

        if ($keyword) {
            $where .= ' and (c.msg like "%' . $keyword . '%" or c.msg like "%' . $keyword . '%")';
        }
        $select = 'c.*, c1.truename as to_name, c2.truename as recv_name, c1.head_img';
        $order = array("c.id", " desc");
        $join[0] = array(
            $this->_customer . " as c1",
            "c1.id = c.user_id",
            "left"
        );
        $join[1] = array(
            $this->_customer . " as c2",
            "c2.id = c.recv_user_id",
            "left"
        );

        $arr = $this->getAllCommon($this->_chat_msg . ' as c', $where, $select, $join, $order, $condition);
        return $arr;
    }

    /**
     * 获取聊天会员
     * @param string $start
     * @param string $limit
     * @return mixed
     */
    public function get($start = '', $limit = '')
    {
        $keyword = $this->input->post("keyword");
        //分页条件
        $condition = array($start, $limit);
        $where = '1=1 and c.is_deleted = 0';

        if ($keyword) {
            $where .= ' and (c.name like "%' . $keyword . '%" or c.truename like "%' . $keyword . '%")';
        }
        $select = 'c.*';
        $order = array("c.id", " desc");
        $arr = $this->getAllCommon($this->_customer . ' as c', $where, $select, '', $order, $condition);
        return $arr;
    }

    public function insert($post)
    {
        return $this->db->insert($this->_customer, $post);
    }

    public function edit($id)
    {
        return $this->db->get_where($this->_customer, "id=$id")->row_array();
    }

    public function update($post)
    {
        return $this->db->update($this->_customer, $post, "id=" . $post['id']);
    }

    public function delete($id)
    {
        return $this->db->delete($this->_customer, array("id" => $id));
    }

    /**
     * 获取新朋友（需要我通过添加朋友请求的）
     * @param $uid
     * @return mixed
     */
    public function getNewFriend($uid)
    {
        $friend = $this->db->select("f.friend_id, c.truename, c.head_img, c.id, f.status")
            ->from($this->_chat_friend . " as f")
            ->join($this->_customer . " as c", "f.user_id = c.id", "left")
            ->where("f.to_user_id=$uid")
            ->get()
            ->result_array();
        return $friend;
    }

    /**
     * 获取我的朋友
     * @param $uid
     * @return mixed
     */
    public function getMyFriend($uid)
    {
        $chat_friend = $this->db->select("*")->from($this->_chat_friend)->where("(to_user_id=$uid or user_id=$uid) and status = 1")->get()->result_array();
        $friend_str = "";
        foreach ($chat_friend as $k => $v)
        {
            if ($v['user_id'] != $uid)
            {
                $friend_str.= $v["user_id"].",";
            }
            if ($v['to_user_id'] != $uid)
            {
                $friend_str.= $v["to_user_id"].",";
            }
        }
        $friend_str = trim($friend_str, ",");
        $friend = $this->db->select("*")->from($this->_customer)->where("id in({$friend_str}) and is_deleted = 0")->get()->result_array();
        return $friend;
    }
}