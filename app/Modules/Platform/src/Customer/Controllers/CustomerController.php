<?php

namespace App\Modules\Platform\Customer\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\Platform\Customer\Contracts\CustomerServiceInterface;
use App\Modules\Platform\Customer\Requests\ListCustomerRequest;
use App\Modules\Platform\Customer\Resources\CustomerDetailResource;
use App\Modules\Platform\Customer\Resources\CustomerListResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends BaseController
{
    public function __construct(
        protected CustomerServiceInterface $service,
    ) {}

    public function index(ListCustomerRequest $request): AnonymousResourceCollection
    {
        return CustomerListResource::collection($this->service->list($request->validated()))
            ->additional(['success' => true]);
    }

    public function show(int $id): CustomerDetailResource
    {
        return new CustomerDetailResource($this->service->findById($id));
    }
}
