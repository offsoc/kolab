@php($support_email = \config('app.support_email'))

<div id="support">
@isset($support_email)
    <div class="row row-cols-1 row-cols-md-2 g-2">
        <div class="col">
@endisset
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title text-center">@lang('theme::support.documentation')</h3>
                    <p class="card-text">
                        @lang('theme::support.doc1')
                        <br/><br/>
                        @lang('theme::support.doc2')
                    </p>
                </div>
                <div class="card-footer text-center">
                    <a href="https://kb.kolab.org" class="btn btn-primary">@lang('theme::support.search-kb')</a>
                </div>
            </div>
@isset($support_email)
        </div>
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title text-center">@lang('theme::support.title')</h3>
                    <p class="card-text">
                        @lang('theme::support.text1')
                        <br/><br/>
                        @lang('theme::support.text2', ['site' => config('app.name')])
                    </p>
                </div>
                <div class="card-footer text-center">
                    <a href="/support/contact" class="btn btn-primary">@lang('theme::support.contact')</a>
                </div>
            </div>
        </div>
@endisset
    </div>
</div>
