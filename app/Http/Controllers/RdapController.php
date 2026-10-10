<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Domain;
use App\Models\Nameserver;
use App\Models\Registrar;
use App\Rdap\RdapSerializer;
use App\Rdap\SearchPattern;
use App\Support\Idn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Public RDAP service (RFC 9082 queries, RFC 9083 responses).
 */
class RdapController extends Controller
{
    public function __construct(private readonly RdapSerializer $serializer) {}

    public function domain(string $name): JsonResponse
    {
        if (! Idn::isValidHostName($name)) {
            return $this->error(400, 'Bad Request', ['Invalid domain name.']);
        }

        $domain = Domain::query()->whereHostName($name)->with(RdapSerializer::DOMAIN_RELATIONS)->first();

        if ($domain === null) {
            return $this->notFound();
        }

        return $this->respond($this->serializer->domainResponse($domain));
    }

    public function nameserver(string $name): JsonResponse
    {
        if (! Idn::isValidHostName($name)) {
            return $this->error(400, 'Bad Request', ['Invalid nameserver name.']);
        }

        $nameserver = Nameserver::query()->whereHostName($name)->with('addresses')->first();

        if ($nameserver === null) {
            return $this->notFound();
        }

        return $this->respond($this->serializer->nameserverResponse($nameserver));
    }

    public function entity(string $handle): JsonResponse
    {
        if ($contact = Contact::query()->where('handle', $handle)->first()) {
            return $this->respond($this->serializer->contactResponse($contact));
        }

        if ($registrar = Registrar::query()->where('handle', $handle)->first()) {
            return $this->respond($this->serializer->registrarResponse($registrar));
        }

        return $this->notFound();
    }

    public function domains(Request $request): JsonResponse
    {
        if (! $request->filled('name')) {
            if ($request->hasAny(['nsLdhName', 'nsIp'])) {
                return $this->notImplemented();
            }

            return $this->error(400, 'Bad Request', ['The "name" query parameter is required.']);
        }

        try {
            $pattern = SearchPattern::parse((string) $request->query('name'));
        } catch (InvalidArgumentException $e) {
            return $this->error(400, 'Bad Request', [$e->getMessage()]);
        }

        $limit = $this->limit();
        $domains = $pattern->apply(Domain::query())
            ->with(RdapSerializer::DOMAIN_RELATIONS)
            ->orderBy('name')
            ->limit($limit + 1)
            ->get();

        return $this->respond($this->serializer->domainSearchResponse($domains->take($limit), $domains->count() > $limit));
    }

    public function nameservers(Request $request): JsonResponse
    {
        $limit = $this->limit();
        $query = Nameserver::query()->with('addresses')->orderBy('name')->limit($limit + 1);

        if ($request->filled('name')) {
            try {
                $pattern = SearchPattern::parse((string) $request->query('name'));
            } catch (InvalidArgumentException $e) {
                return $this->error(400, 'Bad Request', [$e->getMessage()]);
            }

            $pattern->apply($query);
        } elseif ($request->filled('ip')) {
            $ip = (string) $request->query('ip');
            $packed = filter_var($ip, FILTER_VALIDATE_IP) ? inet_pton($ip) : false;

            if ($packed === false) {
                return $this->error(400, 'Bad Request', ['Invalid IP address.']);
            }

            $normalized = (string) inet_ntop($packed);
            $query->whereHas('addresses', fn ($q) => $q->whereIn('ip', array_unique([$ip, $normalized])));
        } else {
            return $this->error(400, 'Bad Request', ['The "name" or "ip" query parameter is required.']);
        }

        $nameservers = $query->get();

        return $this->respond($this->serializer->nameserverSearchResponse($nameservers->take($limit), $nameservers->count() > $limit));
    }

    public function entities(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function help(): JsonResponse
    {
        return $this->respond($this->serializer->helpResponse());
    }

    public function fallback(): JsonResponse
    {
        return $this->error(400, 'Bad Request', ['Unsupported RDAP query.']);
    }

    private function limit(): int
    {
        return max(1, (int) config('rdap.search_limit', 50));
    }

    private function notFound(): JsonResponse
    {
        return $this->error(404, 'Not Found', ['The requested object was not found.']);
    }

    private function notImplemented(): JsonResponse
    {
        return $this->error(501, 'Not Implemented', ['This query is not supported by this server.']);
    }

    /**
     * @param  list<string>  $description
     */
    private function error(int $status, string $title, array $description): JsonResponse
    {
        return $this->respond($this->serializer->error($status, $title, $description), $status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function respond(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, [
            'Content-Type' => RdapSerializer::MEDIA_TYPE,
            'Access-Control-Allow-Origin' => '*',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
