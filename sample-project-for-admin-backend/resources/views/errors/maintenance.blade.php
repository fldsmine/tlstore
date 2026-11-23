@extends('errors.layout')

@section('icon', 'ri-tools-fill')
@section('title', 'We’ll be right back')
@section('message', 'Nextel is undergoing scheduled maintenance to serve you better. Please check back shortly — thank you for your patience.')

@section('action')
    <a href="mailto:{{ config('settings.contact.email') }}" class="btn-outline btn-md inline-flex items-center gap-2">
        <i class="ri-mail-line"></i> Contact Support
    </a>
@endsection
