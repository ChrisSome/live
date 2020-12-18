@extends('layouts.admin')

@section('body')
<div class="layui-card">
	@yield('body-title')
	<div class="layui-card-body">
		<div class="layui-container">
			<div class="layui-row">
				<div class="layui-col-md9">
					<form class="layui-form" action="" lay-filter="form">
						<div class="layui-form-item">
							<label class="layui-form-label">标题</label>
							<div class="layui-input-block">
								<input type="text" name="title" placeholder="备注" autocomplete="off" class="layui-input">
							</div>
						</div>

						<div class="layui-form-item">
							<label class="layui-form-label">内容</label>
							<div class="layui-input-block">
								<textarea name="content" id="content_message" cols="30" rows="10" disabled>
									{{isset($info) ? $info['content'] : ''}}
								</textarea>
							</div>
						</div>
{{--						<div class="layui-form-item">--}}
{{--							<label class="layui-form-label">审核状态</label>--}}
{{--							<div class="layui-input-block">--}}
{{--								<select name="status">--}}
{{--									@foreach([ 3 => '审核拒绝', 4 => '审核通过', 5=>'举报成功', 6=>'发布'] as $k=>$v)--}}
{{--										<option value="{{$k}}" @if($info['status'] == $k) selected @endif>{{$v}}</option>--}}
{{--									@endforeach--}}
{{--								</select>--}}
{{--							</div>--}}
{{--						</div>--}}
						<div class="layui-form-item">
							<label class="layui-form-label">备注</label>
							<div class="layui-input-block">
								<input type="text" name="remark" placeholder="备注" autocomplete="off" class="layui-input">
							</div>
						</div>
						<div class="layui-form-item">
							<div class="layui-input-block">
{{--								<button class="layui-btn" lay-submit lay-filter="submit">立即提交</button>--}}
{{--								<button type="reset" class="layui-btn layui-btn-primary">重置</button>--}}
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection