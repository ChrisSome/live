@extends('layouts.admin')

@section('body')
    <div class="white p20">
        <table class="layui-hide" id="test" lay-filter="test"></table>

        <!-- 表头 -->
        <script type="text/html" id="toolbarDemo">
            @if($role_group->hasRule('auth.role.add'))
            <div class="layui-btn-container">
                <button class="layui-btn layui-btn-normal layui-btn-sm" lay-event="add">发布消息</button>
            </div>
            @endif
        </script>

        <!-- 操作 -->
        <script type="text/html" id="barDemo">

            @if($role_group->hasRule('auth.setting.message.set'))
            <a class="layui-btn layui-btn-xs" lay-event="edit">编辑</a>
            @endif

            @if($role_group->hasRule('auth.setting.message.del'))
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">删除</a>
            @endif

        </script>
        <!-- 状态 -->
        <script type="text/html" id="switchStatus">
            <input type="checkbox" name="status" value="@{{d.id}}" lay-skin="switch" @if(!$role_group->hasRule('auth.rule.set')) disabled="off" @endif lay-text="启动|禁用" lay-filter="status" @{{ d.status == 1 ? 'checked' : '' }}>
        </script>

    </div>
@endsection


@section('javascriptFooter')
    <script>
        layui.use('table', function(){
            var table = layui.table, form = layui.form;

            var datatable = table.render({
                elem: '#test'
                ,url:'/setting/message/list'
                ,method:'post'
                ,toolbar: '#toolbarDemo'
                ,title: '消息类型'
                ,cols: [[
                    {field:'id', title:'ID', width:80, fixed: 'left'}
                    ,{field:'title', title:'标题', width:220}
                    ,{field:'cate_name', title:'分类名称', width:220}
                    ,{field:'status', title:'是否启用', templet: '#switchStatus', width:100}
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
                        location.href = "/setting/message/add";
                        break;
                };
            });


            form.on('switch(status)', function(obj){
                let datajson = {key:'status', value:obj.elem.checked ? '1':'0'};

                $.post('/setting/message/set/' + this.value ,datajson,function(data){
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
                    case 'del':
                        layer.confirm('真的删除行么', function(index){
                            $.post('/setting/message/del/' + data.id ,'',function(data){
                                layer.close(message);
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
                            ,content: '/setting/message/edit/' + data.id
                            ,area:['800px', '700px']
                        });
                        break;
                }
            });
        });
    </script>
@endsection
