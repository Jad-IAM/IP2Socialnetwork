<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\Statistics\Api\Controller;

use Carbon\Carbon;
use DateTime;
use Exception;
use Flarum\Discussion\Discussion;
use Flarum\Http\Exception\InvalidParameterException;
use Flarum\Http\RequestUtil;
use Flarum\Post\Post;
use Flarum\Post\RegisteredTypesScope;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ShowStatisticsData implements RequestHandlerInterface
{
    /**
     * The amount of time to cache lifetime statistics data for in seconds.
     */
    public static int $lifetimeStatsCacheTtl = 300;

    /**
     * The amount of time to cache timed statistics data for in seconds.
     */
    public static int $timedStatsCacheTtl = 900;

    protected array $entities = [];

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected CacheRepository $cache
    ) {
        $this->entities = [
            'users' => [User::query(), 'joined_at'],
            'discussions' => [Discussion::query(), 'created_at'],
            'posts' => [Post::where('type', 'comment')->withoutGlobalScope(RegisteredTypesScope::class), 'created_at']
        ];
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        // Must be an admin to get statistics data -- this is only visible on the admin
        // control panel.
        $actor->assertAdmin();

        $query = $request->getQueryParams();

        $reportingPeriod = Arr::get($query, 'period');
        $model = Arr::get($query, 'model');
        $customDateRange = Arr::get($query, 'dateRange');

        return new JsonResponse($this->getResponse($model, $reportingPeriod, $customDateRange));
    }

    private function getResponse(?string $model, ?string $period, ?array $customDateRange): array
    {
        if ($period === 'lifetime') {
            return $this->getLifetimeStatistics();
        }

        if (! Arr::exists($this->entities, $model)) {
            throw new InvalidParameterException('A model must be specified');
        }

        if ($period === 'custom') {
            $start = (int) $customDateRange['start'];
            $end = (int) $customDateRange['end'];

            if (! $customDateRange || ! $start || ! $end) {
                throw new InvalidParameterException('A custom date range must be specified');
            }

            // Seconds-based timestamps
            $startRange = Carbon::createFromTimestampUTC($start)->toDateTime();
            $endRange = Carbon::createFromTimestampUTC($end)->toDateTime();

            // We can't really cache this
            return $this->getTimedCounts($this->entities[$model][0], $this->entities[$model][1], $startRange, $endRange);
        }

        return $this->getTimedStatistics($model);
    }

    private function getLifetimeStatistics(): array
    {
        return $this->cache->remember('flarum-subscriptions.lifetime_stats', self::$lifetimeStatsCacheTtl, function () {
            return array_map(function ($entity) {
                return $entity[0]->count();
            }, $this->entities);
        });
    }

    private function getTimedStatistics(string $model): array
    {
        return $this->cache->remember("flarum-subscriptions.timed_stats.$model", self::$lifetimeStatsCacheTtl, function () use ($model) {
            return $this->getTimedCounts($this->entities[$model][0], $this->entities[$model][1]);
        });
    }

    private function getTimedCounts(Builder $query, string $column, ?DateTime $startDate = null, ?DateTime $endDate = null): array
    {
        $diff = $startDate && $endDate ? $startDate->diff($endDate) : null;

        if (! isset($startDate)) {
            // need -12 months and period before that
            $startDate = new DateTime('-2 years');
        } else {
            // If the start date is custom, we need to include an equal amount beforehand
            // to show the data for the previous period.
            $startDate = (new Carbon($startDate))->subtract($diff)->toDateTime();
        }

        if (! isset($endDate)) {
            $endDate = new DateTime();
        }

        $formats = match ($query->getConnection()->getDriverName()) {
            'pgsql' => ['YYYY-MM-DD HH24:00:00', 'YYYY-MM-DD'],
            default => ['%Y-%m-%d %H:00:00', '%Y-%m-%d'],
        };

        // if within the last 24 hours, group by hour
        $format = "CASE WHEN $column > ? THEN '$formats[0]' ELSE '$formats[1]' END";

        $dbFormattedDatetime = match ($query->getConnection()->getDriverName()) {
            'sqlite' => "strftime($format, $column)",
            'pgsql' => "TO_CHAR($column, $format)",
            'mysql', 'mariadb' => "DATE_FORMAT($column, $format)",
            default => throw new Exception('Unsupported database driver'),
        };

        $results = $query
            ->selectRaw(
                $dbFormattedDatetime.' as time_group',
                [new DateTime('-25 hours')]
            )
            ->selectRaw('COUNT(id) as count')
            ->where($column, '>', $startDate)
            ->where($column, '<=', $endDate)
            ->groupBy('time_group')
            ->pluck('count', 'time_group');

        $timed = [];

        $results->each(function ($count, $time) use (&$timed) {
            $time = new DateTime($time);
            $timed[$time->getTimestamp()] = (int) $count;
        });

        return $timed;
    }
}
