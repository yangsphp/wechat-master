<style>
    .table>tbody>tr>td{
        vertical-align: middle !important;
    }
</style>
<section class="content-header">
    <h1>
        交谈消息
    </h1>
    <ol class="breadcrumb">
        <li><a href="<?php echo site_url('admin/index')?>"><i class="fa fa-dashboard"></i> 控制台</a></li>
        <li class="active">交谈消息</li>
    </ol>
</section>
<section class="content">
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header" style="border-bottom: 1px solid #ddd;padding-top: 10px;">
                    <h3 class="box-title">
<!--                        <button class="btn btn-primary btn-xs" onclick="showModal()">-->
<!--                            <i class="fa fa-plus"></i>-->
<!--                            添加会员-->
<!--                        </button>-->
                    </h3>
                    <div class="box-tools" style="padding: 10px">
                        <div class="input-group">
                            <input type="text" id="keyword" name="table_search" class="form-control input-sm pull-right" style="width: 150px;" placeholder="Search">
                            <div class="input-group-btn">
                                <button class="btn btn-sm btn-default" onclick="search()"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box-body">
                    <table style="" id="adminTable" class="table table-hover radius" cellspacing="0" width="100%">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>发送人</th>
                            <th>接收人</th>
                            <th>状态</th>
                            <th>消息</th>
                            <th>发送时间</th>
                        </tr>
                        </thead>

                        <tbody>

                        </tbody>
                    </table>
                </div><!-- /.box-body-->
            </div>
        </div>
    </div>
</section>
<script>
    var initTable = null;
    $(document).ready(function () {
        $.dataTablesSettings = {
            bPaginate: true, // 翻页功能
            bProcessing: false,
            serverSide: true, // 启用服务器端分页
            ajax: function (data, callback, settings) {
                showLoading("正在加载...");
                // 封装请求参数
                var param = {};
                param.limit = data.length;// 页面显示记录条数，在页面显示每页显示多少项的时候
                param.start = data.start;// 开始的记录序号
                param.page = (data.start / data.length) + 1;// 当前页码

                //搜索字段。。。
                param.keyword = $("#keyword").val();

                $.ajax({
                    type: 'post',
                    url: siteUrl + '/customer/getMessage',
                    data: param,
                    dataType: 'json',
                    success: function (res) {
                        var returnData = {};
                        returnData.draw = parseInt(data.draw);// 这里直接自行返回了draw计数器,应该由后台返回
                        returnData.recordsTotal = res.total;
                        returnData.recordsFiltered = res.total;// 后台不实现过滤功能，每次查询均视作全部结果
                        returnData.data = res.data;

                        callback(returnData);
                    },
                    error: function (XMLHttpRequest, textStatus, errorThrown) {
                        hideLoading();
                        alert("获取失败");
                    }
                });
            },
            columns: [{
                data: "id",
            }, {
                data: function (mdata) {
                    return `<img style="width: 30px;margin-right: 10px;" src="`+mdata.head_img+`"/>`+mdata.to_name
                }
            },{
                data: "recv_name",
            }, {
                data: function (mdata) {
                    let html = "";
                    if (mdata.is_read == 1)
                    {
                        html += `<small class="badge bg-green" style="cursor: pointer;">已读</small>`;
                    }else {
                        html += `<small class="badge bg-yellow" style="cursor: pointer;">未读</small>`;
                    }
                    return html;
                }
            }, {
                data: "msg",
            }, {
                data: "date_entered",
            }],
            fnInitComplete: function (oSettings, json) {
                hideLoading();
                // 全选、反选
                //checkedOrNo('checkbox0', 'select_checkbox');
            },
            drawCallback: function () {
                hideLoading();
            },
            columnDefs: [{
                "orderable": false,
                "targets": 0
            }],
        };
        initTable = $("#adminTable").dataTable($.dataTablesSettings);

        $('#keyword').on('keyup', function (event) {
            if (event.keyCode == "13") {
                // 回车执行查询
                initTable.api().ajax.reload();
            }
        });
    });

    function search() {
        initTable.api().ajax.reload();
    }

    function showModal(id=0) {
        showLoading();
        $.get(siteUrl + "/customer/add?id="+id, function (data) {
            hideLoading();
            var add_customer = layer.open({
                type: 1,
                title: data.title,
                area: '530px',
                closeBtn: 2,
                shadeClose: false,
                shade: false,
                offset: 'auto',
                shade: [0.3, '#000'],
                content: data.html,
                cancel: function () {

                }
            });
            $("#close-modal").on("click", function () {
                closeLayer(add_customer);
            });
            $("#submit-form").on("click", function () {
                doSubmit(add_customer)
            });
        }, 'json');
    }
    function doSubmit(add_customer) {
        var obj = $("#form");
        loadT = layer.msg('正在提交数据...', { time: 0, icon: 16, shade: [0.3, '#000'] });
        $.post(siteUrl+"/customer/add_op", obj.serialize(), function (res) {
            if (res.code == 0) {
                layer.msg(res.msg, {icon: 1});
                closeLayer(add_customer);
                initTable.api().draw(false);
            }else {
                layer.msg(res.msg, {icon: 2});
            }
        }, "json");
    }

    function del(id) {
        var delete_customer = layer.open({
            type: 1,
            title: "信息",
            area: '300px',
            closeBtn: 2,
            shadeClose: false,
            shade: false,
            offset: 'auto',
            shade: [0.3, '#000'],
            content: `<form class="bt-form pd20 pb70" id="form"><div class="line">您确定要删除吗？</div><div class="bt-form-submit-btn"><button type="button" class="btn btn-sm btn-my" id="close-modal">关闭</button><button type="button" class="btn btn-sm btn-success" id="submit-form">提交</button></div> </form>`,
            cancel: function () {

            },
            success() {
                $("#close-modal").on("click", function () {
                    closeLayer(delete_customer);
                });
                $("#submit-form").on("click", function () {
                    doDelete(id, delete_customer)
                });
            }
        });
    }
    function doDelete(id, delete_customer) {
        loadT = layer.msg('正在提交数据...', { time: 0, icon: 16, shade: [0.3, '#000'] });
        $.get(siteUrl+"/customer/delete?id="+id, function (res) {
            if (res.code == 0) {
                layer.msg(res.msg, {icon: 1});
                closeLayer(delete_customer);
                initTable.api().draw(false);
            } else{
                layer.msg(res.msg, {icon: 2});
            }
        }, "json");
    }

    function setPassword(obj) {
        var flag = $(obj).hasClass("fa-eye");
        var oSpan = $(obj).prev()
        if (flag) {
            oSpan.html(oSpan.data("password"));
            $(obj).removeClass("fa-eye").addClass("fa-eye-slash");
        } else {
            oSpan.html("*******");
            $(obj).addClass("fa-eye").removeClass("fa-eye-slash");
        }
    }
</script>
