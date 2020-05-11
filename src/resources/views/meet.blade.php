@extends('layouts.app')
@section('title', "Meet")
@section('content')
<div id="app">
    <menu-component mode="header"></menu-component>
    <app-component></app-component>
    <div class="filler"></div>
    <menu-component mode="footer"></menu-component>
</div>
@endsection
