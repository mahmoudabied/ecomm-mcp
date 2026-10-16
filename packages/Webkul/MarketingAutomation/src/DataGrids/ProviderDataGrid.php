<?php

namespace Webkul\MarketingAutomation\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ProviderDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     *
     * @return Builder
     */
    public function prepareQueryBuilder()
    {
        return DB::table('automation_provider_connections')
            ->select(
                'id',
                'name',
                'message_channel',
                'driver',
                'sender',
                'status',
                'last_tested_at'
            );
    }

    /**
     * Add the columns shown in the overview.
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('marketing_automation::app.providers.datagrid.id'),
            'type'       => 'integer',
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('marketing_automation::app.providers.datagrid.name'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'message_channel',
            'label'      => trans('marketing_automation::app.providers.datagrid.channel'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'driver',
            'label'      => trans('marketing_automation::app.providers.datagrid.driver'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'sender',
            'label'      => trans('marketing_automation::app.providers.datagrid.sender'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'status',
            'label'      => trans('marketing_automation::app.providers.datagrid.status'),
            'type'       => 'string',
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                $badges = [
                    'connected' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                    'error'     => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                    'untested'  => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
                ];

                $badge = $badges[$row->status] ?? $badges['untested'];

                return "<span class=\"inline-block px-2 py-0.5 rounded text-xs font-medium {$badge}\">"
                    .e(trans("marketing_automation::app.providers.statuses.{$row->status}"))
                    .'</span>';
            },
        ]);

        $this->addColumn([
            'index'           => 'last_tested_at',
            'label'           => trans('marketing_automation::app.providers.datagrid.last-tested'),
            'type'            => 'datetime',
            'filterable'      => true,
            'filterable_type' => 'datetime_range',
            'sortable'        => true,
        ]);
    }

    /**
     * Add the row actions.
     */
    public function prepareActions()
    {
        if (bouncer()->hasPermission('marketing.automation.settings')) {
            $this->addAction([
                'icon'   => 'icon-edit',
                'title'  => trans('marketing_automation::app.providers.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.marketing.automation.providers.show', $row->id),
            ]);

            $this->addAction([
                'icon'   => 'icon-delete',
                'title'  => trans('marketing_automation::app.providers.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.marketing.automation.providers.delete', $row->id),
            ]);
        }
    }
}
