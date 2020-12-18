@extends('layouts.admin')

@section('stylesheet')

    <title>@section('title')赛事直播系统 @endsection</title>

    <link rel="stylesheet" href="/layui/css/layui.css"  media="all">
    <link href="/css/bootstrap.min.css?v=3.3.6" rel="stylesheet">
    <link href="/css/font-awesome.min.css?v=4.4.0" rel="stylesheet">
    <link href="/css/animate.css" rel="stylesheet">
    <link href="/css/style.css?v=4.1.0" rel="stylesheet">
@endsection

@section('header')
    @include(  'layouts.front.header' );
@endsection
@section('body')
    <div class="layui-fluid">
        <div class="layui-row" style="">
            @include('layouts.front.left')
            <div class="layui-col-md9">
                @yield('content','主要内容');
            </div>
        </div>
    </div>

    <div id="option"  style="display: none;">
        <textarea name="content_message" id="content_message" cols="30" rows="10"></textarea>
        <input type="text" name="phone" id="phone" required  lay-verify="required" placeholder="请输入号码" autocomplete="off" class="layui-input">
    </div>

@endsection


@section('javascriptFooter')
    <script src="/js/jquery.min.js?v=2.1.4"></script>
    <script src="/js/bootstrap.min.js?v=3.3.6"></script>
    <script src="/js/plugins/metisMenu/jquery.metisMenu.js"></script>
    <script src="/js/plugins/slimscroll/jquery.slimscroll.min.js"></script>
    <script src="/layer/layer.min.js"></script>
    <script src="/layui/layui.all.js"></script>
    <!-- 自定义js -->
    <script src="/js/hAdmin.js?v=4.1.0"></script>
    <script>

        function openOption()
        {
            var _index = layui.layedit.build('content_message');
            layer.open({
                type : 1,
                title : "投诉建议",
                area : [ '600px', '460px' ],
                content : $("#option"),
                btn: ['确定', '取消'],
                yes: function(index){
                    layer.close(index);
                    $.post('/api/user/option', {'content': layui.layedit.getContent(_index), 'phone': $('#phone').val()}, function(res) {
                        console.log(res);
                    })

                },
                btn2: function(){

                },

            });
        }

    </script>
@endsection