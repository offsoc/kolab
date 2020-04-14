@extends('layouts.app')
@section('title', "Home")
@section('content')
<div id="app">
    <menu-component mode="header"></menu-component>
    <app-component></app-component>
    <div class="filler"></div>
    <menu-component mode="footer"></menu-component>
</div>
@endsection
