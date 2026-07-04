<?php

namespace Config;

use App\Libraries\Jwt;
use App\Libraries\Vault;
use App\Services\ApprovalService;
use App\Services\AuditService;
use App\Services\BotReportService;
use App\Services\ConsoleClient;
use App\Services\DeploymentRequestService;
use App\Services\DevRequestWorkflowService;
use App\Services\FlowInboundService;
use App\Services\PlaywrightWorkerService;
use App\Services\RepoRegistryService;
use App\Services\SafetyGuardService;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\AiProviderInterface;
use App\Services\Github\GitHubServiceFactory;
use App\Services\Github\GitHubServiceInterface;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function vault(bool $getShared = true): Vault
    {
        if ($getShared) {
            return static::getSharedInstance('vault') ?? static::vault(false);
        }
        return new Vault();
    }

    public static function jwt(bool $getShared = true): Jwt
    {
        if ($getShared) {
            return static::getSharedInstance('jwt') ?? static::jwt(false);
        }
        return new Jwt();
    }

    public static function auditService(bool $getShared = true): AuditService
    {
        if ($getShared) {
            return static::getSharedInstance('auditService') ?? static::auditService(false);
        }
        return new AuditService();
    }

    public static function safetyGuard(bool $getShared = true): SafetyGuardService
    {
        if ($getShared) {
            return static::getSharedInstance('safetyGuard') ?? static::safetyGuard(false);
        }
        return new SafetyGuardService();
    }

    public static function repoRegistry(bool $getShared = true): RepoRegistryService
    {
        if ($getShared) {
            return static::getSharedInstance('repoRegistry') ?? static::repoRegistry(false);
        }
        return new RepoRegistryService();
    }

    public static function devRequestWorkflow(bool $getShared = true): DevRequestWorkflowService
    {
        if ($getShared) {
            return static::getSharedInstance('devRequestWorkflow') ?? static::devRequestWorkflow(false);
        }
        return new DevRequestWorkflowService();
    }

    public static function approvalService(bool $getShared = true): ApprovalService
    {
        if ($getShared) {
            return static::getSharedInstance('approvalService') ?? static::approvalService(false);
        }
        return new ApprovalService();
    }

    public static function deploymentRequestService(bool $getShared = true): DeploymentRequestService
    {
        if ($getShared) {
            return static::getSharedInstance('deploymentRequestService') ?? static::deploymentRequestService(false);
        }
        return new DeploymentRequestService();
    }

    public static function botReportService(bool $getShared = true): BotReportService
    {
        if ($getShared) {
            return static::getSharedInstance('botReportService') ?? static::botReportService(false);
        }
        return new BotReportService();
    }

    public static function flowInboundService(bool $getShared = true): FlowInboundService
    {
        if ($getShared) {
            return static::getSharedInstance('flowInboundService') ?? static::flowInboundService(false);
        }
        return new FlowInboundService();
    }

    public static function playwrightWorker(bool $getShared = true): PlaywrightWorkerService
    {
        if ($getShared) {
            return static::getSharedInstance('playwrightWorker') ?? static::playwrightWorker(false);
        }
        return new PlaywrightWorkerService();
    }

    public static function consoleClient(bool $getShared = true): ConsoleClient
    {
        if ($getShared) {
            return static::getSharedInstance('consoleClient') ?? static::consoleClient(false);
        }
        return new ConsoleClient();
    }

    public static function github(bool $getShared = true): GitHubServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('github') ?? static::github(false);
        }
        return GitHubServiceFactory::make();
    }

    public static function aiProvider(bool $getShared = true): AiProviderInterface
    {
        if ($getShared) {
            return static::getSharedInstance('aiProvider') ?? static::aiProvider(false);
        }
        return AiProviderFactory::make();
    }
}
