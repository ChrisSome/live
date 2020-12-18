@extends('admin.auth.postBase')

@section('body-title')
    <div class="layui-card-header">审核帖子</div>
    <div class="white p20">
        <table class="layui-hide" id="test" lay-filter="test"></table>

        <!-- 操作 -->
        <script type="text/html" id="barDemo">

            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">删除</a>

        </script>
    </div>
@endsection

@section('javascriptFooter')
    <script src="/layui/layui.all.js"></script>
    <script>
        //展示下拉框
        layui.use('form', function(){
            var layedit = layui.layedit;

            layedit.set({
                uploadImage: {
                    url: '/upload' //接口url
                    ,type: 'post' //默认post
                }
            });
            var _index = layedit.build('content_message');
            var form = layui.form;
            var form_field;
            form.val("form", {
                "title": "{{ $info['title'] }}"
                ,"remark": "{{ $info['remark'] }}"
            });

            function callback(data) {
                if(data.code != 0) {
                    layer.msg(info.msg);
                } else {
                    layer.msg('编辑成功', {time:1000}, function(){
                        form_field.form.reset();
                        location.href='/setting/message'
                    });
                }
            }

            //监听提交
            form.on('submit(submit)', function(data){
                form_field = data;
                data.field.content = layui.layedit.getContent(_index);
                $.post('/user/post/edit/'+{{ $info['id'] }},data.field,function(info){
                    if(info.code != 0) {
                        layer.msg(info.msg);
                    } else {
                        layer.msg('编辑成功',{time:1000},function(){
                            parent.layer.close(parent.layer.getFrameIndex(window.name));
                            parent.refresh();
                        });
                    }
                });

                return false;
            });
        });

        layui.use('table', function () {
            var table = layui.table, form = layui.form;
            datatable = table.render({

                elem: '#test'
                , url: '/user/post/comment/list/'+{{$info['id']}}
                , method: 'post'
                , toolbar: '#toolbarDemo'
                , title: '评论列表'

                , cols: [[
                    {field: 'id', title: 'ID', width: 80, fixed: 'left'}
                    , {field: 'content', title: '内容', width: 200}
                    , {field: 'nickname', title: '用户昵称', width: 150}
                    , {field: 'quote_content', title: '应用评论', width: 150}
                    , {field: 'content', title: '评论/回复内容', width: 150}
                    , {field: 'created_at', title: '发布时间', width: 150}
                    , {fixed: 'right', title: '操作', toolbar: '#barDemo', width: 100}
                ]]
                ,	parseData:function(res){
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
                            $.post('/user/post/comment/del/' + data.id, '', function (data) {
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
                            , content: '/user/post/edit/' + data.id
                            , area: ['800px', '620px']
                        });
                        break;
                }
            });
        });
    </script>
@endsection