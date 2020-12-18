<div class="layui-col-md2" style="margin-left: -20px;">
    <ul class="layui-nav layui-nav-tree" lay-filter="test" style="height: 900px; overflow-y: hidden;">
        <!-- 侧边导航: <ul class="layui-nav layui-nav-tree layui-nav-side"> -->
        @if ($action == 'broad')
            <li class="layui-nav-item layui-nav-itemed">
                <a href="javascript:;">赛事区</a>
                <dl class="layui-nav-child">
                    <dd><a href="javascript:;">体育</a></dd>
                    <dd><a href="javascript:;">小侃</a></dd>
                    <dd><a href="">你懂的</a></dd>
                </dl>
            </li>
        @elseif($action == 'post')
            <li class="layui-nav-item layui-nav-itemed">
                <a href="javascript:;">查看帖子</a>
                <dl class="layui-nav-child">
                    <dd class="layui-this" ><a href="javascript:;">帖子列表</a></dd>
                    <dd><a href="/api/post/add">发布帖子</a></dd>
                </dl>
            </li>
        @elseif($module == 'user')
            <li class="layui-nav-item layui-nav-itemed">
                <a href="javascript:;">个人中心</a>
                <dl class="layui-nav-child">
                    <dd @if($action == 'base') class="layui-this" @endif><a href="/api/user/safe">基本信息</a></dd>
                    <dd @if($action == 'safe') class="layui-this" @endif><a href="/api/user/safe">安全中心</a></dd>
                    <dd @if($action == 'personal') class="layui-nav-item layui-this" @endif>
                        <a href="/api/user/edit">编辑信息</a></dd>
                </dl>
            </li>
        @endif

        <li class="layui-nav-item layui-nav-itemed">
            <a href="javascript:;">关于我们</a>
            <dl class="layui-nav-child">
                <dd><a href="javascript:;">联系我们</a></dd>
                <dd><a href="javascript:;">更多精彩</a></dd>
            </dl>
        </li>
    </ul>
</div>