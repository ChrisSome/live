@extends('layouts.admin')

@section('body')
    <div class="white p20">
        <table class="layui-hide" id="test" lay-filter="test"></table>

        <!-- 表头 -->
        <script type="text/html" id="toolbarDemo">
            <div class="layui-btn-container" style="margin-top: 10px;">
                <form class="layui-form"  action="" method="post" lay-filter="form">
                    <div class="layui-row">
                        <div class="layui-col-md1">
                            <div class="layui-inline">
                                <input class="layui-input layui-btn-sm" name="title" id="title" autocomplete="off" placeholder="帖子标题">
                            </div>
                        </div>
                        <div class="layui-col-md1">
                            <div class="layui-inline">
                                <input class="layui-input layui-btn-sm" name="nickaname" id="nickaname" autocomplete="off" placeholder="昵称">
                            </div>
                        </div>
                        <div class="layui-col-md2" style="margin-left: 10px; margin-right:10px;">
                            <div class="layui-inline" style="width: 100%;"> <!-- 注意：这一层元素并不是必须的 -->
                                <input type="text" class="layui-input layui-btn-sm" id="time" placeholder="时间">
                            </div>
                        </div>
                        <div class="layui-col-md2">
                            <button class="layui-btn layui-btn-sm searchBtn" type="button">搜索</button>
                        </div>
                    </div>
                </form>
            </div>
        </script>

        <!-- 状态 -->
        <script type="text/html" id="switchStatus">
            <input type="checkbox" name="status" value="@{{d.id}}" lay-skin="switch"
                   @if(!$role_group->hasRule('user.post.set')) disabled="off" @endif lay-text="待审核|审核通过"
                   lay-filter="status" @{{ d.status== 1 ? 'checked' : '' }}>
        </script>


        <!-- 操作 -->
        <script type="text/html" id="barDemo">
            @if($role_group->hasRule('user.post.set'))
            <a class="layui-btn layui-btn-xs" lay-event="edit">查看</a>
            @endif
            @if($role_group->hasRule('user.post.del'))
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="post_confirm">标记处理</a>
            @endif
            @if($role_group->hasRule('user.post.del'))
            <a class="layui-btn layui-btn-xs" lay-event="post_del">删除</a>
            @endif
        </script>
    </div>
@endsection


@section('javascriptFooter')
    <script>
        layui.use('laydate', function(){
            var laydate = layui.laydate;

            //执行一个laydate实例
            laydate.render({
                elem: '#time' //指定元素
                ,range: true
                ,theme: 'grid'
                ,calendar: true
            });

        });
        var datatable;
        $(document).on('click','.searchBtn',function () {
            datatable.reload({
                where:{
                    nickname:$('#nickaname').val().trim(),
                    title:$('#title').val().trim(),
                    time: $('#time').val()
                },
                page:{
                    curr:1
                }
            })
        });
        layui.use('table', function () {
            var table = layui.table, form = layui.form;

            datatable = table.render({
                elem: '#test'
                , url: '/user/post/list'
                , method: 'post'
                , toolbar: '#toolbarDemo'
                , title: '用户列表'
                , where: {where: 'accusation'}
                , cols: [[
                    {field: 'id', title: 'ID', width: 80, fixed: 'left'}
                    , {field: 'title', title: '帖子标题', width: 200}
                    , {field: 'nickname', title: '发帖人昵称', width: 150}
                    , {field: 'respon_number', title: '回复数', width: 150}
                    , {field: 'created_at', title: '提交时间', width: 180}
                    , {field: 'status', title: '状态', width: 100, templet: function(res) {
                            return res.status == 1 ? '处理中' : '已处理'
                        }}
                    , {fixed: 'right', title: '操作', toolbar: '#barDemo', width: 290}
                ]]
                ,	parseData:function(res){
                    console.log(res)
                    //这个函数非常实用，是2.4.0版本新增的，当后端返回的数据格式不符合layuitable需要的格式，用这个函数对返回的数据做处理，在2.4.0版本之前，只能通过修改table源码来解决这个问题
                    $('#nickaname').val(res.params.nickname)
                    layui.use('laydate', function(){
                        var laydate = layui.laydate;

                        //执行一个laydate实例
                        laydate.render({
                            elem: '#time' //指定元素
                            ,range: true
                            ,theme: 'grid'
                            ,calendar: true
                            ,sInitValue: true
                            ,value: res.params.time
                        });

                    });
                    return {
                        code: res.code,
                        msg:res.status,
                        count:res.count, //总页数，用于分页
                        data:res.data
                    }
                }
                , defaultToolbar: []
                , page: true
            });


            //头工具栏事件
            table.on('toolbar(test)', function (obj) {
                var checkStatus = table.checkStatus(obj.config.id);
                switch (obj.event) {
                    case 'post_confirm':
                        layer.confirm('确定举报不成功吗', function (index) {
                            $.post('/post/cstatus/' + data.id + '/status/2', '', function (data) {
                                layer.close(index);
                                if (data.code != 0) {
                                    layer.msg(data.msg);
                                } else {
                                    obj.del();
                                }
                            });
                        });
                        break;
                }
                ;
            });


            window.refresh = function()
            {

                datatable.reload();
            }
            form.on('switch(status)', function (obj) {
                let datajson = {key: 'status', value: obj.elem.checked ? '1' : '0'};

                $.post('/user/post/set/' + this.value, datajson, function (data) {
                    if (data.code != 0) {
                        layer.msg(data.msg);
                        obj.elem.checked = !obj.elem.checked;
                        form.render();
                    }
                });
            });


            //监听行工具事件
            table.on('tool(test)', function (obj) {
                var data = obj.data;
                switch (obj.event) {
                    case 'del':
                        layer.confirm('真的删除行么', function (index) {
                            $.post('/user/post/del/' + data.id, '', function (data) {
                                layer.close(index);
                                if (data.code != 0) {
                                    layer.msg(data.msg);
                                } else {
                                    obj.del();
                                }
                            });
                        });
                        break;
                    case 'confirm':
                        layer.confirm('确定通过？', function (index) {
                            $.post('/user/post/confirm/' + data.id, '', function (data) {
                                layer.close(index);
                                if (data.code != 0) {
                                    layer.msg(data.msg);
                                } else {
                                    obj.del();
                                }
                            });
                        });
                        break;
                    case 'comment':
                        layer.open({
                            title: '查看评论'
                            , type: 2
                            , content: '/user/post/comment/' + data.id
                            , area: ['800px', '620px']
                        });
                        break;
                    case 'edit':
                        layer.open({
                            title: '编辑权限'
                            , type: 2
                            , content: '/user/post/edit/' + data.id
                            , area: ['800px', '620px']
                        });
                        break;
                }
            });
        });
    </script>
@endsection
