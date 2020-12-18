@extends('layouts.admin')

@section('body')
    <div class="white p20">
        <table class="layui-hide" id="test" lay-filter="test"></table>

        <!-- 表头 -->
        <script type="text/html" id="toolbarDemo">
            @if($role_group->hasRule('auth.user.add'))
            <div class="layui-btn-container">
                <button class="layui-btn layui-btn-normal layui-btn-sm" lay-event="add">新增</button>
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
            <a class="layui-btn layui-btn-xs" lay-event="edit">查看</a>
            @endif

            @if($role_group->hasRule('auth.user.del'))
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">编辑</a>
            @endif
        </script>
    </div>
@endsection


@section('javascriptFooter')
    <script>
        var datatable;
        $(document).on('click','.searchBtn',function () {
            datatable.reload({
                where:{
                    mobile:$('#mobile').val().trim()
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
                , url: '/setting/notice'
                , method: 'post'
                , toolbar: '#toolbarDemo'
                , title: '用户列表'
                , cols: [[
                    {field: 'id', title: 'ID', width: 80, fixed: 'left'}
                    , {field: 'title', title: '标题', width: 200}
                    , {field: 'content', title: '内容', width: 300}
                    , {field: 'created_at', title: '发布时间', width: 200}
                    , {fixed: 'right', title: '操作', toolbar: '#barDemo', width: 150}
                ]]
                ,	parseData:function(res){
                    //这个函数非常实用，是2.4.0版本新增的，当后端返回的数据格式不符合layuitable需要的格式，用这个函数对返回的数据做处理，在2.4.0版本之前，只能通过修改table源码来解决这个问题
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
