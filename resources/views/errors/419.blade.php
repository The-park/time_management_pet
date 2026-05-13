@extends('errors.layout')

@section('title', 'Session expired')
@section('code', '419')
@section('heading_class', 'text-amber-300')
@section('heading', 'Session expired')
@section('description')
    Your session timed out for security. Refresh the page or sign back in to keep going.
@endsection
