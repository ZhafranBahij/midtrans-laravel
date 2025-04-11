@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Product') }}</div>

                <div class="card-body">
                    @if (count($errors) > 0)
                        @foreach ( $errors->all() as $message )
                            <div class="alert alert-danger" role="alert">
                                {{ $message }}
                            </div>
                        @endforeach
                    @endif
                    <form action="{{ route('product.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                          <label for="name" class="form-label">name</label>
                          <input type="text" class="form-control" id="name" aria-describedby="name" name="name" value="{{ $data->name }}" required>
                        </div>
                        <div class="mb-3">
                          <label for="price" class="form-label">price</label>
                          <input type="text" class="form-control" id="price" aria-describedby="price" name="price" value="{{ $data->price }}" required>
                        </div>
                        <div class="mb-3">
                          <label for="description" class="form-label">description</label>
                          <input type="text" class="form-control" id="description" aria-describedby="description" name="{{ $data->description }}" value="description" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit</button>
                        <a class="btn btn-secondary" href="{{ route('product.index') }}">Back</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
