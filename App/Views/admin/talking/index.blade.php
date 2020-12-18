@extends('layouts.admin')

@section('body')
    <div class="white p20">
        <table class="layui-hide" id="test" lay-filter="test"></table>

        <!-- 表头 -->
        <script type="text/html" id="toolbarDemo">
            @if($role_group->hasRule('auth.user.add'))
{{--            <div class="layui-btn-container">--}}
{{--                <button hidden class="layui-btn layui-btn-normal layui-btn-sm" lay-event="add">添加用户</button>--}}
{{--            </div>--}}
            <div class="layui-btn-container" style="margin-top: 10px;">
                <form class="layui-form" action="" lay-filter="form">
                    <div class="layui-row">
                        <div class="layui-col-md2">
                            <div class="layui-inline">
                                <input class="layui-input layui-btn-sm" name="initiative_nicname" id="initiative_nicname" autocomplete="off" placeholder="举报人昵称">
                            </div>

                        </div>
                        <div class="layui-col-md2">
                            <div class="layui-inline">
                                <input class="layui-input layui-btn-sm" name="passive_nicname" id="passive_nicname" autocomplete="off" placeholder="被举报人昵称">
                            </div>
                        </div>
                        <div class="layui-col-md2">
                            <div class="layui-inline" style="width: 50px">
{{--                                <input class="layui-input layui-btn-sm" name="status" id="status" autocomplete="off" placeholder="举报人昵称">--}}
                                <select name="status" lay-verify="" lay-filter="test" id="status">
                                    <option value="">请选择一个城市</option>
                                    <option value="010">北京</option>
                                    <option value="021">上海</option>
                                    <option value="0571">杭州</option>
                                </select>
                            </div>
                        </div>
                        <div class="layui-col-md2" style="margin-left: 10px; margin-right:10px;">
                            <div class="layui-inline" style="width: 100%;"> <!-- 注意：这一层元素并不是必须的 -->
                                <input type="text" class="layui-input layui-btn-sm" id="time" placeholder="时间">
                            </div>
                        </div>
                        <div class="layui-col-md2">
                            <button class="layui-btn layui-btn-sm searchBtn">搜索</button>
                        </div>
                    </div>
                </form>
            </div>
            @endif
        </script>

        <!-- 状态 -->
        <script type="text/html" id="switchStatus">
            <input type="checkbox" name="status" value="@{{d.id}}" lay-skin="switch"
                   @if(!$role_group->hasRule('auth.rule.set')) disabled="off" @endif lay-text="启动|禁用"
                   lay-filter="status" @{{ d.status== 1 ? 'checked' : '' }}>
        </script>


        <!-- 操作 -->
        <script type="text/html" id="barDemo">
            @if($role_group->hasRule('auth.user.set'))
            <a class="layui-btn layui-btn-xs" lay-event="edit">编辑</a>
            @endif

            @if($role_group->hasRule('auth.user.del'))
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">删除</a>
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
                    initiative_nicname:$('#initiative_nicname').val().trim(),
                    passive_nicname:$('#passive_nicname').val().trim(),
                    status:$('#status').val().trim(),
                    created_at:$('#time').val().trim()
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
                , url: '/content/list'
                , method: 'post'
                , toolbar: '#toolbarDemo'
                , title: '聊天内容列表'
                , cols: [[
                    {field: 'id', title: 'ID', width: 80, fixed: 'left'}
                    , {field: 'initiative_id', title: '举报人id', width: 100}
                    , {field: 'initiative_nicname', title: '举报人昵称', width: 150}
                    , {field: 'passive_id', title: '被举报人id', width: 100}
                    , {field: 'count_num', title: '被举报次数', width: 100}
                    , {field: 'content', title: '内容', width: 220}
                    , {field: 'created_at', title: '举报时间', width: 150}
                    , {field: 'status', title: '状态', templet: '#switchStatus', width: 100}
                    , {fixed: 'right', title: '操作', toolbar: '#barDemo', width: 150}
                ]]
                ,	parseData:function(res){
                    //这个函数非常实用，是2.4.0版本新增的，当后端返回的数据格式不符合layuitable需要的格式，用这个函数对返回的数据做处理，在2.4.0版本之前，只能通过修改table源码来解决这个问题
                    $('#mobile').val(res.params.mobile)
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
                    case 'add_rule':
                        location.href = '/user/add/' + data.id;
                        break;
                    case 'del':
                        layer.confirm('真的删除行么', function (index) {
                            $.post('/user/del/' + data.id, '', function (data) {
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
