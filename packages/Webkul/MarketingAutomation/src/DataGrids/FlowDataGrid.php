<?php

namespace Webkul\MarketingAutomation\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class FlowDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     *
     * @return Builder
     */
    public function prepareQueryBuilder()
    {
        $since = now()->subDays(30)->toDateString();

        return DB::table('automation_flows')
            ->select(
                'automation_flows.id',
                'automation_flows.name',
                'automation_flows.trigger_type',
                'automation_flows.type',
                'automation_flows.status',
            )
            ->selectRaw('(SELECT GROUP_CONCAT(channels.code SEPARATOR ", ") FROM automation_flow_channels INNER JOIN channels ON channels.id = automation_flow_channels.channel_id WHERE automation_flow_channels.flow_id = automation_flows.id) as channels')
            ->selectRaw('(SELECT COALESCE(SUM(count), 0) FROM automation_step_stats WHERE automation_step_stats.flow_id = automation_flows.id AND automation_step_stats.metric = ? AND automation_step_stats.date >= ?) as enrolled_30d', ['enrolled', $since])
            ->selectRaw('(SELECT COALESCE(SUM(revenue), 0) FROM automation_step_stats WHERE automation_step_stats.flow_id = automation_flows.id AND automation_step_stats.date >= ?) as revenue_30d', [$since]);
    }

    /**
     * Add the columns shown in the overview.
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('marketing_automation::app.flows.datagrid.id'),
            'type'       => 'integer',
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('marketing_automation::app.flows.datagrid.name'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'trigger_type',
            'label'      => trans('marketing_automation::app.flows.datagrid.trigger'),
            'type'       => 'string',
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => trans("marketing_automation::app.flows.triggers.{$row->trigger_type}"),
        ]);

        $this->addColumn([
            'index'      => 'type',
            'label'      => trans('marketing_automation::app.flows.datagrid.type'),
            'type'       => 'string',
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => trans("marketing_automation::app.flows.types.{$row->type}"),
        ]);

        $this->addColumn([
            'index'      => 'status',
            'label'      => trans('marketing_automation::app.flows.datagrid.status'),
            'type'       => 'string',
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                $badges = [
                    'active' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                    'paused' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
                    'draft'  => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
                ];

                $badge = $badges[$row->status] ?? $badges['draft'];

                return "<span class=\"inline-block px-2 py-0.5 rounded text-xs font-medium {$badge}\">"
                    .e(trans("marketing_automation::app.flows.statuses.{$row->status}"))
                    .'</span>';
            },
        ]);

        $this->addColumn([
            'index'      => 'channels',
            'label'      => trans('marketing_automation::app.flows.datagrid.channels'),
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'enrolled_30d',
            'label'      => trans('marketing_automation::app.flows.datagrid.enrolled'),
            'type'       => 'integer',
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'revenue_30d',
            'label'      => trans('marketing_automation::app.flows.datagrid.revenue'),
            'type'       => 'decimal',
            'sortable'   => false,
        ]);
    }

    /**
     * Add the row actions.
     */
    public function prepareActions()
    {
        if (bouncer()->hasPermission('marketing.automation.flows.manage')) {
            $this->addAction([
                'icon'   => 'icon-edit',
                'title'  => trans('marketing_automation::app.flows.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.marketing.automation.flows.edit', $row->id),
            ]);

            $this->addAction([
                'icon'   => 'icon-copy',
                'title'  => trans('marketing_automation::app.flows.datagrid.duplicate'),
                'method' => 'POST',
                'url'    => fn ($row) => route('admin.marketing.automation.flows.duplicate', $row->id),
            ]);

            $this->addAction([
                'icon'   => 'icon-delete',
                'title'  => trans('marketing_automation::app.flows.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.marketing.automation.flows.delete', $row->id),
            ]);
        }
    }
}
