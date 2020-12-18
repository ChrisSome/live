@extends('layouts.admin')

@section('body')
    <div class="white p20">
        <table class="layui-hide" id="test" lay-filter="test"></table>

        <!-- 表头 -->
        <script type="text/html" id="toolbarDemo">
            @if($role_group->hasRule('auth.role.add'))
            <div class="layui-btn-container">
                <button class="layui-btn layui-btn-normal layui-btn-sm" lay-event="add">添加消息分类</button>
            </div>
            @endif
        </script>

        <!-- 操作 -->
        <script type="text/html" id="barDemo">
            @if($role_group->hasRule('auth.setting.category.add'))
            <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="add_rule">添加</a>
            @endif

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
                ,url:'/setting/cate/list'
                ,method:'post'
                ,toolbar: '#toolbarDemo'
                ,title: '消息类型'
                ,cols: [[
                    {field:'id', title:'ID', width:80, fixed: 'left'}
                    ,{field:'name', title:'权限名', width:220}
                    ,{field:'pid', title:'上级id', width:220 @if($role_group->hasRule('auth.setting.category.set')), event:'edit_node' @endif}
                    ,{field:'pname', title:'上级名称', width:100}
                    ,{field:'created_at', title:'创建时间'}
                    ,{field:'updated_at', title:'更新时间'}
                    ,{fixed: 'right', title:'操作', toolbar: '#barDemo', width: 180}
                ]]
                ,defaultToolbar:[]
                // ,page: true
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
                        location.href = "/setting/cate/add";
                        break;
                };
            });


            form.on('switch(status)', function(obj){
                let datajson = {key:'status', value:obj.elem.checked ? '1':'0'};

                $.post('/setting/cate/set/' + this.value ,datajson,function(data){
                    if(data.code != 0) {
                        layer.msg(data.msg);
                        obj.elem.checked = !obj.elem.checked;
                        form.render();
                    }
                });
            });


            //监听行工具事件
            table.on('tool(test)', function(obj){
                var data = obj.data;
                switch(obj.event){
                    case 'add_rule':
                        location.href = '/setting/cate/add/' + data.id;
                        break;
                    case 'del':
                        layer.confirm('真的删除行么', function(index){
                            $.post('/setting/cate/del/' + data.id ,'',function(data){
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
                            ,content: '/setting/cate/edit/' + data.id
                            ,area:['500px', '270px']
                        });
                        break;
                    case 'edit_node':
                        layer.prompt({
                            formType: 2
                            ,value: data.node
                        }, function(value, index){
                            layer.close(index);
                            let datajson = {key:'node', value:value};
                            $.post('/setting/cate/set/' + data.id ,datajson,function(data){
                                if(data.code != 0) {
                                    layer.msg(data.msg);
                                } else {
                                    obj.update({
                                        node: value
                                    });
                                }
                            });
                        });
                        break;
                }
            });
        });
    </script>
@endsection
