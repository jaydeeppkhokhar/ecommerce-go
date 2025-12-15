<?php

namespace Botble\Table\BulkActions;

use Botble\Base\Contracts\BaseModel;
use Botble\Base\Events\DeletedContentEvent;
use Botble\Base\Exceptions\DisabledInDemoModeException;
use Botble\Base\Facades\BaseHelper;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Ecommerce\Facades\OrderHelper;
use Botble\Ecommerce\Models\Order;
use Botble\Table\Abstracts\TableBulkActionAbstract;
use Illuminate\Database\Eloquent\Model;

class ConfirmOrderBulkAction extends TableBulkActionAbstract
{
    protected bool $silent = false;

    public function __construct()
    {
        $this
            ->label('Confirm Order')
            ->confirmationModalButton('Confirm Order')
            ->beforeDispatch(function (): void {
                if (BaseHelper::hasDemoModeEnabled()) {
                    throw new DisabledInDemoModeException();
                }
            });
    }

    public function dispatch(BaseModel|Model $model, array $ids): BaseHttpResponse
    {
        $model->newQuery()->whereKey($ids)->each(function (Order $order): void {
            OrderHelper::confirmOrder($order);
        });

        return BaseHttpResponse::make()->withUpdatedSuccessMessage();
    }
}
