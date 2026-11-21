@extends('layouts.sede', ['titulo' => 'Mis expedientes'])

@section('contenido')
    <nav class="migas" aria-label="Estás aquí"><ol><li><a href="{{ route('sede.carpeta') }}">Mi carpeta</a></li><li aria-current="page">Mis expedientes</li></ol></nav>
    <h1>Mis expedientes</h1>
    @include('sede.partials.tabla-expedientes', ['expedientes' => $expedientes])
    {{ $expedientes->links() }}
@endsection
