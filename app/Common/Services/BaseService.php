<?php

namespace App\Common\Services;

use App\Common\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseService
{
    /**
     * Execute a callback within a database transaction.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws BusinessException
     */
    protected function executeInTransaction(callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (BusinessException $exception) {
            throw $exception;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            // Re-throw ModelNotFoundException to be handled by global exception handler
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Transaction failed', [
                'service' => static::class,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw new BusinessException(
                message: 'Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.',
                errorCode: 'TRANSACTION_FAILED',
                httpStatusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                context: ['original_message' => $exception->getMessage()],
                previous: $exception,
            );
        }
    }
}
