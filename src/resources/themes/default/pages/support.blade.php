<div id="support" class="card">
    <div class="card-body">
        <h3 class="card-title text-center">@lang('theme::support.title')</h3>
            <p class="card-text text-justify">
                @lang('theme::support.text1')
                <br />
                <br />
                @lang('theme::support.text2', ['site' => config('app.name')])
            </p>
    </div>
    <div class="card-footer text-center">
        <a href="/support/contact" class="btn btn-primary">@lang('theme::support.title')</a>
    </div>
</div>
