<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class BaseController extends Controller
{
    /** @var CLIRequest|IncomingRequest */
    protected $request;

    protected $helpers = ['response', 'validation'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    /** @return array{id: int, email: string, roles: array<int, string>}|null */
    protected function currentUser(): ?array
    {
        return $this->request->buildUser ?? null;
    }

    protected function currentUserId(): ?int
    {
        return $this->currentUser()['id'] ?? null;
    }

    protected function jsonInput(): array
    {
        $body = $this->request->getBody();
        if (! is_string($body) || $body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}
