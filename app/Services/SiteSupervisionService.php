<?php

namespace App\Services;

use App\Enums\SiteReportStatus;
use App\Enums\SnagStatus;
use App\Models\Project;
use App\Models\SiteReport;
use App\Models\Snag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Site meeting records and snags. Numbers are allocated inside the writing
 * transaction with a project row lock, so a manual retry or two tabs cannot
 * produce the same reference twice.
 */
class SiteSupervisionService
{
    /** @param array<string, mixed> $data */
    public function createReport(Project $project, User $actor, array $data): SiteReport
    {
        return DB::transaction(function () use ($project, $actor, $data): SiteReport {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);
            $report = SiteReport::create([
                'project_id' => $locked->id,
                'number' => $this->nextNumber($locked, 'site_reports', 'CR', 'number'),
                'meeting_date' => $data['meeting_date'],
                'status' => SiteReportStatus::Draft,
                'agenda' => $this->text($data['agenda'] ?? null),
                'decisions' => $this->text($data['decisions'] ?? null),
                'next_meeting_on' => $this->nullableDate($data['next_meeting_on'] ?? null),
                'created_by' => $actor->id,
            ]);
            $this->trace($actor, $report, 'site.report_created', ['number' => $report->number]);

            return $report;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateReport(SiteReport $report, User $actor, array $data): SiteReport
    {
        return DB::transaction(function () use ($report, $actor, $data): SiteReport {
            $locked = SiteReport::query()->lockForUpdate()->findOrFail($report->id);
            if ($locked->isFinal()) {
                throw ValidationException::withMessages(['report' => __('phase8.report_final_readonly')]);
            }
            $locked->update([
                'meeting_date' => $data['meeting_date'],
                'agenda' => $this->text($data['agenda'] ?? null),
                'decisions' => $this->text($data['decisions'] ?? null),
                'next_meeting_on' => $this->nullableDate($data['next_meeting_on'] ?? null),
            ]);
            $this->trace($actor, $locked, 'site.report_updated', ['number' => $locked->number]);

            return $locked;
        }, 3);
    }

    public function finalize(SiteReport $report, User $actor): SiteReport
    {
        return DB::transaction(function () use ($report, $actor): SiteReport {
            $locked = SiteReport::query()->lockForUpdate()->findOrFail($report->id);
            if ($locked->isFinal()) {
                return $locked;
            }
            if (trim((string) $locked->decisions) === '' && trim((string) $locked->agenda) === '') {
                throw ValidationException::withMessages(['decisions' => __('phase8.report_needs_content')]);
            }
            $locked->update([
                'status' => SiteReportStatus::Final,
                'finalized_at' => now(),
                'finalized_by' => $actor->id,
            ]);
            $this->trace($actor, $locked, 'site.report_finalized', ['number' => $locked->number]);

            return $locked;
        }, 3);
    }

    public function deleteReport(SiteReport $report, User $actor): void
    {
        DB::transaction(function () use ($report, $actor): void {
            $locked = SiteReport::query()->lockForUpdate()->findOrFail($report->id);
            if ($locked->isFinal()) {
                throw ValidationException::withMessages(['report' => __('phase8.report_final_readonly')]);
            }
            $locked->snags()->update(['site_report_id' => null]);
            $locked->photos()->update(['site_report_id' => null]);
            $this->trace($actor, $locked, 'site.report_deleted', ['number' => $locked->number]);
            $locked->delete();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createSnag(Project $project, User $actor, array $data): Snag
    {
        return DB::transaction(function () use ($project, $actor, $data): Snag {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);
            $snag = Snag::create([
                'project_id' => $locked->id,
                'site_report_id' => $this->reportId($locked, $data['site_report_id'] ?? null),
                'reference' => $this->nextNumber($locked, 'snags', 'R', 'reference'),
                'title' => $data['title'],
                'description' => $this->text($data['description'] ?? null),
                'internal_notes' => $this->text($data['internal_notes'] ?? null),
                'location' => $this->text($data['location'] ?? null),
                'responsible' => $this->text($data['responsible'] ?? null),
                'severity' => $data['severity'],
                'status' => SnagStatus::Open,
                'due_on' => $this->nullableDate($data['due_on'] ?? null),
                'created_by' => $actor->id,
            ]);
            $this->trace($actor, $snag, 'site.snag_created', ['reference' => $snag->reference, 'severity' => $snag->severity]);

            return $snag;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateSnag(Snag $snag, User $actor, array $data): Snag
    {
        return DB::transaction(function () use ($snag, $actor, $data): Snag {
            $locked = Snag::query()->lockForUpdate()->findOrFail($snag->id);
            if ($locked->status === SnagStatus::Validated) {
                throw ValidationException::withMessages(['snag' => __('phase8.snag_validated_readonly')]);
            }
            $locked->update([
                'site_report_id' => $this->reportId($locked->project, $data['site_report_id'] ?? null),
                'title' => $data['title'],
                'description' => $this->text($data['description'] ?? null),
                'location' => $this->text($data['location'] ?? null),
                'responsible' => $this->text($data['responsible'] ?? null),
                'severity' => $data['severity'],
                'due_on' => $this->nullableDate($data['due_on'] ?? null),
            ]);
            $this->trace($actor, $locked, 'site.snag_updated', ['reference' => $locked->reference]);

            return $locked;
        }, 3);
    }

    public function updateSnagNotes(Snag $snag, User $actor, ?string $notes): void
    {
        DB::transaction(function () use ($snag, $actor, $notes): void {
            $locked = Snag::query()->lockForUpdate()->findOrFail($snag->id);
            if ($locked->status === SnagStatus::Validated) {
                throw ValidationException::withMessages(['snag' => __('phase8.snag_validated_readonly')]);
            }
            $locked->update(['internal_notes' => $this->text($notes)]);
            $this->trace($actor, $locked, 'site.snag_notes_updated', ['reference' => $locked->reference]);
        }, 3);
    }

    public function transition(Snag $snag, User $actor, SnagStatus $target): Snag
    {
        return DB::transaction(function () use ($snag, $actor, $target): Snag {
            $locked = Snag::query()->lockForUpdate()->findOrFail($snag->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['snag' => __('phase8.invalid_snag_transition')]);
            }
            $attributes = ['status' => $target];
            if ($target === SnagStatus::Fixed) {
                $attributes['fixed_at'] = now();
            }
            if ($target === SnagStatus::Validated) {
                $attributes['validated_at'] = now();
                $attributes['validated_by'] = $actor->id;
            }
            $locked->update($attributes);
            $this->trace($actor, $locked, 'site.snag_status_changed', ['reference' => $locked->reference, 'status' => $target->value]);

            return $locked;
        }, 3);
    }

    public function deleteSnag(Snag $snag, User $actor): void
    {
        DB::transaction(function () use ($snag, $actor): void {
            $locked = Snag::query()->lockForUpdate()->findOrFail($snag->id);
            if ($locked->status === SnagStatus::Validated) {
                throw ValidationException::withMessages(['snag' => __('phase8.snag_validated_readonly')]);
            }
            $this->trace($actor, $locked, 'site.snag_deleted', ['reference' => $locked->reference]);
            $locked->delete();
        }, 3);
    }

    /**
     * Reference such as CR-2026-0007 or R-2026-0012, scoped to the project and
     * the year so a site archive stays readable without a global counter.
     */
    private function nextNumber(Project $project, string $table, string $prefix, string $column): string
    {
        $year = CarbonImmutable::now(config('app.timezone'))->format('Y');
        $existing = DB::table($table)
            ->where('project_id', $project->id)
            ->where($column, 'like', $prefix.'-'.$year.'-%')
            ->pluck($column)
            ->map(fn ($value) => (int) substr((string) $value, -4))
            ->max() ?? 0;

        return $prefix.'-'.$year.'-'.str_pad((string) ($existing + 1), 4, '0', STR_PAD_LEFT);
    }

    private function reportId(?Project $project, mixed $id): ?int
    {
        if ($id === null || $id === '' || $project === null) {
            return null;
        }

        return SiteReport::query()->where('project_id', $project->id)->whereKey((int) $id)->value('id')
            ?? throw ValidationException::withMessages(['site_report_id' => __('phase8.invalid_reference')]);
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /** @param array<string, mixed> $properties */
    private function trace(User $actor, SiteReport|Snag $model, string $event, array $properties): void
    {
        activity('site')->causedBy($actor)->performedOn($model)->withProperties($properties)->event($event)->log($event);
    }
}
