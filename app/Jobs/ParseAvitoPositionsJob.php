<?php

namespace App\Jobs;

use App\Models\Posting;
use App\Models\PostingPosition;
use App\Services\AvitoParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ParseAvitoPositionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $query, public string $queryUrl, public array $postIds)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        dump(urldecode($this->queryUrl));

        $service = new AvitoParserService();

        $positions = $service->calculatePositions($this->queryUrl, $this->postIds);

        $positionsForSave = $this->preparePositionsForSave($this->query, $positions);
        PostingPosition::query()->upsert($positionsForSave, ['fk_posting_id', 'check_date']);
    }

    private function preparePositionsForSave(string $query, array $positions): array
    {
        $postingsForeignKeyDict = $this->getPostingForeignKeyDict();

        $time = now();
        $total = $positions['total'];
        $positionsForSave = [];

        foreach ($positions['positions'] as $postId => $position) {
            $positionsForSave[] = [
                'fk_posting_id' => $postingsForeignKeyDict[$query][$postId],
                'position' => $position,
                'total' => $total,
                'check_date' => $time->toDateString(),
            ];
        }

        return $positionsForSave;
    }

    private function getPostingForeignKeyDict(): array
    {
        $postings = Posting::query()->select('id as fk_posting_id', 'query', 'query_url', 'post_id')->get();
        $postingsForeignKeyDict = [];
        foreach ($postings as $posting) {
            $postingsForeignKeyDict[$posting->getAttribute('query')][$posting->post_id] = $posting->fk_posting_id;
        }
        return $postingsForeignKeyDict;
    }
}
