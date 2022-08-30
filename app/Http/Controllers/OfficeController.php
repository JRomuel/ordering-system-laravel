<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use Doctrine\Inflector\Rules\Turkish\Rules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request; 
use Illuminate\Validation\Rule;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class OfficeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $offices = Office::query()
            ->where('approval_status', Office::APPROVAL_APPROVED)
            ->where('hidden', false)
            ->when(request('user_id'), fn($builder) => $builder->whereUserId(request('user_id')))
            ->when(request('visitor_id'), 
                fn (Builder $builder) 
                => $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id'))
            )
            ->when(
                request('lat') && request('lng'),
                fn($builder) => $builder->nearestTo(request('lat'), request('lng')),
                fn($builder) => $builder->orderBy('id', 'ASC')
            )
            ->with(['images', 'tags', 'user'])
            ->withCount(['reservations' => fn($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->paginate(20); 
        return OfficeResource::collection(
            $offices
        );   
    } 

    public function show(Office $office): JsonResource
    {
        $office->loadCount(['reservations' => fn($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
        ->load(['images', 'tags', 'user']);
        return OfficeResource::make($office);
    }

    public function create(): JsonResource
    {
        $attributes = validator(request()->all(),
            [
                'title' => ['required','string'],
                'description' => ['required', 'string'],
                'lat' => ['required', 'numeric'],
                'lng' => ['required', 'numeric'],
                'address_line1' => ['required', 'string'],
                'hidden' => ['bool'],
                'price_per_day' => ['required', 'integer', 'min:100'],
                'monthly_discount' => ['integer', 'min:0'],

                'tags' => ['array'],
                'tags.*' => ['integer', Rule::exists('tags', 'id')],
            ]
        )->validate();

        $attributes['approval_status'] = Office::APPROVAL_PENDING;


        $office = auth()->user()->offices()->create(
            Arr::except($attributes, ['tags'])
        );

        $office->tags()->sync($attributes['tags']);

        return OfficeResource::make(
            $office->load(['images', 'tags', 'user'])
        );
    }

    
}