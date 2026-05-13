@extends('errors.layout')

@section('title', 'Page not found')
@section('code', '404')
@section('heading', 'Page not found')
@section('description')
    The page you were looking for doesn't exist, has moved, or is no longer accessible.
    Head back to your dashboard to keep tracking your day.
@endsection
@if (isset($exception) && $exception->getMessage())
    @section('details', e($exception->getMessage()))
@endif
