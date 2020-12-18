<ul class="layui-nav layui-bg-green" lay-filter="">
    <li class="layui-nav-item">
        <a href="">热点新闻</a>
    </li>
    <li @if($action == 'broad') class="layui-nav-item layui-this" @else class='layui-nav-item' @endif>
        <a href="/api/broad/list">赛事区</a>
    </li>
    <li class="layui-nav-item"><a href="javascript: void(0)" onclick="openOption()">投诉建议</a></li>
    <li @if($action == 'personal') class="layui-nav-item layui-this pull-right" @else class='layui-nav-item pull-right' @endif>
        <a href=""><img src="//t.cn/RCzsdCq" class="layui-nav-img">{{$realname}}</a>
        <dl class="layui-nav-child">
            <dd><a href="javascript: void(0)">修改信息</a></dd>
            <dd><a href="/api/user/personal">个人中心</a></dd>
            <dd><a href="javascript:;">安全管理</a></dd>
            <dd><a href="/api/user/logout">退了</a></dd>
        </dl>
    </li>
    <li @if($action == 'post') class="layui-nav-item layui-this" @else class='layui-nav-item' @endif>
        <a href="/api/user/post">社区</a>
    </li>
</ul>