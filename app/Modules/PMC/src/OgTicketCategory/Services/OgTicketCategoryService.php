<?php

namespace App\Modules\PMC\OgTicketCategory\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\OgTicketCategory\Contracts\OgTicketCategoryServiceInterface;
use App\Modules\PMC\OgTicketCategory\Models\OgTicketCategory;
use App\Modules\PMC\OgTicketCategory\Repositories\OgTicketCategoryRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class OgTicketCategoryService extends BaseService implements OgTicketCategoryServiceInterface
{
    public function __construct(
        protected OgTicketCategoryRepository $repository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): OgTicketCategory
    {
        /** @var OgTicketCategory */
        return $this->repository->findById($id);
    }

    public function create(array $data): OgTicketCategory
    {
        if (empty($data['code'])) {
            $data['code'] = Str::slug($data['name']) ?: 'category';
        }

        /** @var OgTicketCategory */
        $category = $this->repository->create($data);

        return $category->refresh();
    }

    public function update(int $id, array $data): OgTicketCategory
    {
        $category = $this->findById($id);
        $category->update($data);

        return $category->refresh();
    }

    public function checkDelete(int $id): array
    {
        $this->findById($id);
        $count = $this->repository->countLinks($id);

        if ($count > 0) {
            return [
                'can_delete' => false,
                'message' => "Không thể xoá: còn {$count} OG ticket đang gắn danh mục này.",
                'link_count' => $count,
            ];
        }

        return [
            'can_delete' => true,
            'message' => '',
            'link_count' => 0,
        ];
    }

    public function delete(int $id): void
    {
        $category = $this->findById($id);
        $count = $this->repository->countLinks($id);

        if ($count > 0) {
            throw new BusinessException(
                "Không thể xoá: còn {$count} OG ticket đang gắn danh mục này.",
                'OG_TICKET_CATEGORY_HAS_LINKS',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['link_count' => $count],
            );
        }

        $category->delete();
    }
}
