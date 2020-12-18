@extends('layouts.admin')

@section('body')
    <div class="white p20">
        <table class="layui-hide" id="test" lay-filter="test"></table>
        <!-- 操作 -->
        <script type="text/html" id="barDemo">
            @if($role_group->hasRule('user.post.comment.confirm'))
            <a class="layui-btn layui-btn-primary layui-btn-xs" lay-event="comment">通过评论</a>
            @endif
            @if($role_group->hasRule('user.post.comment.del'))
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">废弃</a>
            @endif
        </script>
    </div>
@endsection


@section('javascriptFooter')
    <script>
        var _id = '{{$id}}';
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
                , url: '/user/post/comment/list/'+_id
                , method: 'post'
                , toolbar: '#toolbarDemo'
                , title: '评论列表'
                , cols: [[
                    {field: 'id', title: 'ID', width: 80, fixed: 'left'}
                    ,{field: 'user_id', title: '用户id', width: 80, fixed: 'left'}
                    , {field: 'nickname', title: '昵称', width: 100}
                    , {field: 'content', title: '评论内容', width: 150}
                    , {field: 'status', title: '状态', width: 100, templet: function(res) {
                            return res.status == 0 ? '待审核' : (res.status == 1 ? '已通过' : '已废弃')
                        }}
                    , {fixed: 'right', title: '操作', toolbar: '#barDemo', width: 180}
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



            window.refresh = function()
            {

                datatable.reload();
            }

            //监听行工具事件
            table.on('tool(test)', function (obj) {
                var data = obj.data;
                switch (obj.event) {
                    case 'del':
                        layer.confirm('真的要废弃么', function (index) {
                            $.post('/user/post/comment/del/' + data.id, {'status': 3}, function (data) {
                                layer.close(index);
                                if (data.code != 0) {
                                    layer.msg(data.msg);
                                }
                                parent.layer.close(parent.layer.getFrameIndex(window.name));
                            });
                        });
                        break;
                    case 'comment':
                        $.post('/user/post/comment/del/' + data.id, {'status' : 1}, function (data) {
                            if (data.code != 0) {
                                layer.msg(data.msg);
                            }
                            parent.layer.close(parent.layer.getFrameIndex(window.name));
                        });
                        break;
                }
            });
        });
    </script>
@endsection
