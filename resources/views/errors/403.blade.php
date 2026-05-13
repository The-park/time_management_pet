@extends('errors.layout')

@section('title', 'Forbidden')
@section('code', '403')
@section('heading', 'Forbidden')
@section('description')
    You don't have permission to view this page. If you think this is a mistake,
    return to your dashboard and try a different action.
@endsection
@if (isset($exception) && $exception->getMessage())
    @section('details', e($exception->getMessage()))
@endif
