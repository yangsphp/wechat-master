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

    function __construct()
    {
        parent::__construct();
        $this->_customer = $this->config->item("customer");
        $this->_chat = $this->config->item("chat");
    }

    public function login_op($post)
    {
        return $this->db->get_where($this->_customer, array("name" => $post['username'], "password" => $post['password'], "is_deleted" => 0))->row_array();
    }
}