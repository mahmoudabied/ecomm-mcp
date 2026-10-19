<?php

namespace Amacraft\MarketingAutomation\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;
use Amacraft\MarketingAutomation\Services\RecipientMasker;

class EnrolmentDataGrid extends DataGrid
{
    /**
     * Create a new enrolment data grid instance.
     */
    public function __construct(
        protected RecipientMasker $recipientMasker,
    ) {}

    /**
     * Prepare query builder.
     *
     * @return Builder
     */
    public function prepareQueryBuilder()
    {
        $flowId = request('flow_id') ?? (int) request()->route('flow');

        return DB::table('automation_enrolments')
            ->leftJoin('automation_contacts', 'automation_contacts.id', '=', 'automation_enrolments.contact_id')
            ->where('automation_enrolments.flow_id', $flowId)
            ->select(
                'automation_enrolments.id',
                'automation_enrolments.status',
                'automation_enrolments.current_step_key',
                'automation_enrolments.created_at',
                'automation_contacts.email',
                'automation_contacts.phone_e164',
            );
    }

    /**
     * Add the columns shown in the report.
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('marketing_automation::app.reports.enrolments-grid.id'),
            'type'       => 'integer',
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'email',
            'label'      => trans('marketing_automation::app.reports.enrolments-grid.email'),
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
            'closure'    => fn ($row) => e((string) $this->recipientMasker->mask($row->email)),
        ]);

        $this->addColumn([
            'index'      => 'phone_e164',
            'label'      => trans('marketing_automation::app.reports.enrolments-grid.phone'),
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
            'closure'    => fn ($row) => e((string) $this->recipientMasker->mask($row->phone_e164)),
        ]);

        $this->addColumn([
            'index'      => 'status',
            'label'      => trans('marketing_automation::app.reports.enrolments-grid.status'),
            'type'       => 'string',
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => e(trans("marketing_automation::app.flows.statuses.{$row->status}")),
        ]);

        $this->addColumn([
            'index'      => 'current_step_key',
            'label'      => trans('marketing_automation::app.reports.enrolments-grid.current-step'),
            'type'       => 'string',
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'created_at',
            'label'      => trans('marketing_automation::app.reports.enrolments-grid.entered'),
            'type'       => 'datetime',
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'   => 'journey',
            'label'   => trans('marketing_automation::app.reports.journey'),
            'type'    => 'string',
            'closure' => fn ($row) => '<button type="button" class="text-blue-600 underline" data-journey-id="'.$row->id.'">'
                .e(trans('marketing_automation::app.reports.journey'))
                .'</button>',
        ]);
    }
}
