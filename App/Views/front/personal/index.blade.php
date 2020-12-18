@extends('layouts.front')

@section('title')赛事直播系统--个人中心 @endsection

@section('content')
    <form class="layui-form" action="" lay-filter="form" style="margin-top:10px; width: 50%;">
        <div class="layui-form-item">
            <label class="layui-form-label">登陆名</label>
            <div class="layui-input-block">
                <input type="text" name="username" required  lay-verify="required" placeholder="请输入登陆名"
                       value="{{$auth['username']}}"  autocomplete="off" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">手机号</label>
            <div class="layui-input-block">
                <input type="text" name="mobile" required  lay-verify="required" placeholder="请输入登陆名"
                       value="{{$auth['mobile']}}"  autocomplete="off" class="layui-input">
            </div>
        </div>
    </form>
@endsection