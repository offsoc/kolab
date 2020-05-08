@extends('layouts.app')
@section('title', "Meet")
@section('content')
<div id="app">
    <menu-component mode="header"></menu-component>
    <h1>Room '{{ $room }}'</h1>
    <div class="filler"></div>
    <menu-component mode="footer"></menu-component>
</div>
@endsection
