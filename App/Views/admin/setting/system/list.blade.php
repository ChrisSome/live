@extends('layouts.admin')

@section('body')
    <div class="white p20">
        <table class="layui-hide" id="test" lay-filter="test"></table>

        <!-- 表头 -->
        <script type="text/html" id="toolbarDemo">
            @if($role_group->hasRule('auth.setting.system.add'))
            <div class="layui-btn-container">
                <button class="layui-btn layui-btn-normal layui-btn-sm" lay-event="add">添加配置项</button>
            </div>
            @endif
        </script>

        <!-- 操作 -->
        <script type="text/html" id="barDemo">
            @if($role_group->hasRule('auth.setting.category.set'))
            <a class="layui-btn layui-btn-xs" lay-event="edit">编辑</a>
            @endif

            @if($role_group->hasRule('auth.setting.category.del'))
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">删除</a>
            @endif
        </script>
    </div>
@endsection


@section('javascriptFooter')
    <script>
        layui.use('table', function(){
            var table = layui.table, form = layui.form;

            var datatable = table.render({
                elem: '#test'
                ,url:'/setting/sys/list'
                ,method:'post'
                ,toolbar: '#toolbarDemo'
                ,title: '消息类型'
                ,cols: [[
                    {field:'id', title:'ID', width:80, fixed: 'left'}
                    ,{field:'sys_key', title:'键', width:220}
                    ,{field:'sys_value', title:'值', width: 440}
                    ,{field:'created_at', title:'创建时间'}
                    ,{field:'updated_at', title:'更新时间'}
                    ,{fixed: 'right', title:'操作', toolbar: '#barDemo', width:150}
                ]]
                ,defaultToolbar:[]
                 ,page: true
            });

            window.refresh = function()
            {
                datatable.reload();
            }

            //头工具栏事件
            table.on('toolbar(test)', function(obj){
                var checkStatus = table.checkStatus(obj.config.id);
                switch(obj.event){
                    case 'add':
                        location.href = "/setting/sys/add";
                        break;
                };
            });



            //监听行工具事件
            table.on('tool(test)', function(obj){
                var data = obj.data;
                switch(obj.event){
                    case 'del':
                        layer.confirm('真的删除行么', function(index){
                            $.post('/setting/sys/del/' + data.id ,'',function(data){
                                layer.close(index);
                                if(data.code != 0) {
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
                            ,type: 2
                            ,content: '/setting/sys/edit/' + data.id
                            ,area:['500px', '280px']
                        });
                        break;
                }
            });
        });
    </script>
@endsection
