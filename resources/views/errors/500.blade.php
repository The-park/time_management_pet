@extends('errors.layout')

@section('title', 'Something went wrong')
@section('code', '500')
@section('heading', 'Something went wrong')
@section('description')
    The server hit an unexpected error. Your logged time is safe — head back to your
    dashboard and try again. If this keeps happening, drop a note via Contact.
@endsection
