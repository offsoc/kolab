@extends('layouts.app')
@section('title', "Meet")
@section('content')
<div id="app">
    <menu-component mode="header"></menu-component>
    <h1>This is a front page</h1>
    <p>
        Probably a good location to self-promote and nudge people to sign up.
    </p>
    <div class="filler"></div>
    <menu-component mode="footer"></menu-component>
</div>
@endsection
