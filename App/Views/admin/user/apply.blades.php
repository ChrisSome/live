@extends('layouts.admin')

@section('body')
    <div class="white p20">
        <table class="layui-hide" id="test" lay-filter="test"></table>

        <!-- 表头 -->
        <script type="text/html" id="toolbarDemo">
            <div class="layui-btn-container" style="margin-top: 10px;">
                <form class="layui-form" action="" lay-filter="form">
                    <div class="layui-row">
                        <div class="layui-col-md2">
                            <div class="layui-inline">
                                <input class="layui-input layui-btn-sm" name="nickname" id="nickname" autocomplete="off" placeholder="用户昵称">
                            </div>
                        </div>
                        <div class="layui-col-md2">
                            <div class="layui-inline">
                                <select name="pre_status">
                                    <option value="1">处理中</option>
                                    <option value="2">申请通过</option>
                                    <option value="3">申请拒绝</option>
                                </select>
                            </div>
                        </div>
                        <div class="layui-col-md2" style="margin-left: 10px; margin-right:10px;">
                            <div class="layui-inline" style="width: 100%;"> <!-- 注意：这一层元素并不是必须的 -->
                                <input type="text" class="layui-input layui-btn-sm" id="updated_at" placeholder="时间">
                            </div>
                        </div>
                        <div class="layui-col-md2">
                            <button class="layui-btn layui-btn-sm searchBtn">搜索</button>
                        </div>
                    </div>
                </form>
            </div>
        </script>


        <!-- 操作 -->
        <script type="text/html" id="barDemo">
            @if($role_group->hasRule('auth.user.set'))
            <a class="layui-btn layui-btn-xs" lay-event="apply_confirm">审核通过</a>
            @endif

            @if($role_group->hasRule('auth.user.del'))
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="apply_refuse">审核驳回</a>
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
                , url: '/user/apply'
                , method: 'post'
                , toolbar: '#toolbarDemo'
                , title: '用户列表'
                , cols: [[
                    {field: 'id', title: '用户ID', width: 80, fixed: 'left'}
                    , {field: 'nickname', title: '昵称', width: 150}
                    , {field: 'pre_nickname', title: '申请昵称', width: 150}
                    , {field: 'pre_photo',  title: '申请头像', width: 220, templet:function (res) {
                            return '<div><img src=' + res.pre_photo +'> </div>'
                        }}
                    , {field: 'updated_at', title: '申请时间', width: 250}
                    , {field: 'status', title: '状态', width: 100, templet: function(res) {
                            return res.status == 1 ? '处理中' : '审核通过'
                        }}
                    , {fixed: 'right', title: '操作', toolbar: '#barDemo', width: 200}
                ]]
                ,	parseData:function(res){
                    //这个函数非常实用，是2.4.0版本新增的，当后端返回的数据格式不符合layuitable需要的格式，用这个函数对返回的数据做处理，在2.4.0版本之前，只能通过修改table源码来解决这个问题
                    $('#imgtmp').attr('src', res.params.pre_photo)
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
                    case 'add':
                        location.href = "/user/add";
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

                $.post('/user/set/' + this.value, datajson, function (data) {
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
                    case 'apply_confirm':
                        layer.confirm('确定审核通过吗', function (index) {
                            $.post('/user/apply/' + data.id + '/pre_status/2', '', function (data) {
                                layer.close(index);
                                if (data.code != 0) {
                                    layer.msg(data.msg);
                                } else {
                                    obj.del();
                                }
                            });
                        });
                        break;
                    case 'confirm_refuse':
                        layer.confirm('确认审核拒绝吗', function (index) {
                            $.post('/user/apply/' + data.id + '/pre_status/3', '', function (data) {
                                layer.close(index);
                                if (data.code != 0) {
                                    layer.msg(data.msg);
                                } else {
                                    obj.del();
                                }
                            });
                        });
                        break;
                    case 'edit':
                        layer.open({
                            title: '编辑权限'
                            , type: 2
                            , content: '/user/edit/' + data.id
                            , area: ['500px', '505px']
                        });
                        break;
                }
            });
        });
    </script>
@endsection
