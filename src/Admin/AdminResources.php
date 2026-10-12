<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Consultation;
use App\Entity\ConsultationContribution;
use App\Entity\Event;
use App\Entity\KeyFigure;
use App\Entity\MediaGallery;
use App\Entity\MenuItem;
use App\Entity\NewsletterSubscriber;
use App\Entity\OfficialDocument;
use App\Entity\Page;
use App\Entity\Service;
use App\Entity\ServiceRequest;
use App\Entity\Setting;
use App\Entity\User;
use App\Entity\Video;

/**
 * Catalogue des ressources de l'administration : une entrée par liste (menu latéral, tableau, droits).
 * Les groupes du menu : contents, services, media, requests, structure, users.
 */
final class AdminResources
{
    public const array GROUPS = ['contents', 'services', 'media', 'requests', 'structure', 'users'];

    /** @var array<string, AdminResource>|null */
    private ?array $resources = null;

    /**
     * @return array<string, AdminResource>
     */
    public function all(): array
    {
        return $this->resources ??= $this->build();
    }

    public function get(string $id): ?AdminResource
    {
        return $this->all()[$id] ?? null;
    }

    /**
     * Ressource d'une entité (ou de son nom de classe), pour les Voters.
     */
    public function forEntity(object|string $entity): ?AdminResource
    {
        $class = \is_object($entity) ? $entity::class : $entity;
        foreach ($this->all() as $resource) {
            if (is_a($class, $resource->entity, true)) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * @return array<string, AdminResource>
     */
    private function build(): array
    {
        $title = static fn (string $path = 'title'): array => ['key' => 'title', 'label' => 'admin.column.title', 'path' => $path, 'type' => 'title'];
        $status = ['key' => 'status', 'label' => 'admin.column.status', 'path' => 'status', 'type' => 'status', 'sort' => 'e.status'];
        $updated = ['key' => 'updatedAt', 'label' => 'admin.column.updated', 'path' => 'updatedAt', 'type' => 'datetime', 'sort' => 'e.updatedAt'];

        $resources = [
            new AdminResource('articles', 'contents', 'admin.resource.articles', 'ri:newspaper-line', Article::class, 'ROLE_EDITOR', [
                $title(),
                ['key' => 'type', 'label' => 'admin.column.type', 'path' => 'type', 'type' => 'enum', 'sort' => 'e.type'],
                $status,
                ['key' => 'publishedAt', 'label' => 'admin.column.published', 'path' => 'publishedAt', 'type' => 'date', 'sort' => 'e.publishedAt'],
                $updated,
            ], translationSearchFields: ['title', 'excerpt'], defaultSort: 'e.updatedAt', workflow: true, newRoute: 'app_admin_article_new', editRoute: 'app_admin_article_edit', translatable: true),
            new AdminResource('documents', 'contents', 'admin.resource.documents', 'ri:file-text-line', OfficialDocument::class, 'ROLE_EDITOR', [
                $title(),
                ['key' => 'type', 'label' => 'admin.column.type', 'path' => 'type', 'type' => 'enum', 'sort' => 'e.type'],
                ['key' => 'reference', 'label' => 'admin.column.reference', 'path' => 'reference', 'type' => 'text', 'sort' => 'e.reference'],
                $status,
                ['key' => 'documentDate', 'label' => 'admin.column.document_date', 'path' => 'documentDate', 'type' => 'date', 'sort' => 'e.documentDate'],
            ], searchFields: ['reference'], translationSearchFields: ['title', 'summary'], defaultSort: 'e.updatedAt', workflow: true, newRoute: 'app_admin_document_new', editRoute: 'app_admin_document_edit', translatable: true),
            new AdminResource('events', 'contents', 'admin.resource.events', 'ri:calendar-event-line', Event::class, 'ROLE_EDITOR', [
                $title(),
                ['key' => 'type', 'label' => 'admin.column.type', 'path' => 'type', 'type' => 'enum', 'sort' => 'e.type'],
                ['key' => 'startsAt', 'label' => 'admin.column.starts', 'path' => 'startsAt', 'type' => 'datetime', 'sort' => 'e.startsAt'],
                $status,
            ], translationSearchFields: ['title', 'location'], defaultSort: 'e.startsAt', workflow: true, translatable: true),
            new AdminResource('key-figures', 'contents', 'admin.resource.key_figures', 'ri:bar-chart-2-line', KeyFigure::class, 'ROLE_EDITOR', [
                $title('label'),
                ['key' => 'value', 'label' => 'admin.column.value', 'path' => 'value', 'type' => 'text'],
                ['key' => 'dataDate', 'label' => 'admin.column.data_date', 'path' => 'dataDate', 'type' => 'date', 'sort' => 'e.dataDate'],
                $status,
            ], translationSearchFields: ['label', 'source'], defaultSort: 'e.position', workflow: true, translatable: true),
            new AdminResource('consultations', 'contents', 'admin.resource.consultations', 'ri:chat-poll-line', Consultation::class, 'ROLE_EDITOR', [
                $title(),
                ['key' => 'opensAt', 'label' => 'admin.column.opens', 'path' => 'opensAt', 'type' => 'datetime', 'sort' => 'e.opensAt'],
                ['key' => 'closesAt', 'label' => 'admin.column.closes', 'path' => 'closesAt', 'type' => 'datetime', 'sort' => 'e.closesAt'],
                $status,
            ], translationSearchFields: ['title', 'summary'], defaultSort: 'e.opensAt', workflow: true, translatable: true),

            new AdminResource('services', 'services', 'admin.resource.services', 'ri:service-line', Service::class, 'ROLE_ADMIN', [
                $title(),
                ['key' => 'audience', 'label' => 'admin.column.audience', 'path' => 'audience', 'type' => 'enum', 'sort' => 'e.audience'],
                ['key' => 'online', 'label' => 'admin.column.online', 'path' => 'online', 'type' => 'bool', 'sort' => 'e.online'],
                $status,
            ], translationSearchFields: ['title', 'summary'], defaultSort: 'e.position', workflow: true, translatable: true),

            new AdminResource('galleries', 'media', 'admin.resource.galleries', 'ri:gallery-line', MediaGallery::class, 'ROLE_EDITOR', [
                $title(),
                $status,
                ['key' => 'publishedAt', 'label' => 'admin.column.published', 'path' => 'publishedAt', 'type' => 'date', 'sort' => 'e.publishedAt'],
            ], translationSearchFields: ['title', 'description'], defaultSort: 'e.updatedAt', workflow: true, translatable: true),
            new AdminResource('videos', 'media', 'admin.resource.videos', 'ri:video-line', Video::class, 'ROLE_EDITOR', [
                $title(),
                ['key' => 'youtubeId', 'label' => 'admin.column.youtube', 'path' => 'youtubeId', 'type' => 'text'],
                $status,
            ], searchFields: ['youtubeId'], translationSearchFields: ['title', 'description'], defaultSort: 'e.position', workflow: true, translatable: true),

            new AdminResource('requests', 'requests', 'admin.resource.requests', 'ri:inbox-line', ServiceRequest::class, 'ROLE_PRESS', [
                ['key' => 'trackingNumber', 'label' => 'admin.column.tracking_number', 'path' => 'trackingNumber', 'type' => 'text', 'sort' => 'e.trackingNumber'],
                ['key' => 'service', 'label' => 'admin.column.service', 'path' => 'service', 'type' => 'related-title'],
                ['key' => 'status', 'label' => 'admin.column.status', 'path' => 'status', 'type' => 'request-status', 'sort' => 'e.status'],
                ['key' => 'createdAt', 'label' => 'admin.column.received', 'path' => 'createdAt', 'type' => 'datetime', 'sort' => 'e.createdAt'],
            ], searchFields: ['trackingNumber', 'email'], defaultSort: 'e.createdAt', showRoute: 'app_admin_request_show', exportable: true),
            new AdminResource('contributions', 'requests', 'admin.resource.contributions', 'ri:message-3-line', ConsultationContribution::class, 'ROLE_ADMIN', [
                ['key' => 'name', 'label' => 'admin.column.name', 'path' => 'name', 'type' => 'text', 'sort' => 'e.name'],
                ['key' => 'organization', 'label' => 'admin.column.organization', 'path' => 'organization', 'type' => 'text'],
                ['key' => 'consultation', 'label' => 'admin.column.consultation', 'path' => 'consultation', 'type' => 'related-title'],
                ['key' => 'createdAt', 'label' => 'admin.column.received', 'path' => 'createdAt', 'type' => 'datetime', 'sort' => 'e.createdAt'],
            ], searchFields: ['name', 'organization'], defaultSort: 'e.createdAt', showRoute: 'app_admin_contribution_show', exportable: true),
            new AdminResource('subscribers', 'requests', 'admin.resource.subscribers', 'ri:mail-line', NewsletterSubscriber::class, 'ROLE_ADMIN', [
                ['key' => 'email', 'label' => 'admin.column.email', 'path' => 'email', 'type' => 'text', 'sort' => 'e.email'],
                ['key' => 'locale', 'label' => 'admin.column.language', 'path' => 'locale', 'type' => 'text'],
                ['key' => 'status', 'label' => 'admin.column.status', 'path' => 'status', 'type' => 'enum', 'sort' => 'e.status'],
                ['key' => 'subscribedAt', 'label' => 'admin.column.subscribed', 'path' => 'subscribedAt', 'type' => 'datetime', 'sort' => 'e.subscribedAt'],
            ], searchFields: ['email'], defaultSort: 'e.subscribedAt', exportable: true),

            new AdminResource('pages', 'structure', 'admin.resource.pages', 'ri:pages-line', Page::class, 'ROLE_ADMIN', [
                $title(),
                $status,
                $updated,
            ], translationSearchFields: ['title', 'body'], defaultSort: 'e.updatedAt', workflow: true, newRoute: 'app_admin_page_new', editRoute: 'app_admin_page_edit', translatable: true),
            new AdminResource('menus', 'structure', 'admin.resource.menus', 'ri:menu-line', MenuItem::class, 'ROLE_ADMIN', [
                $title('label'),
                ['key' => 'menu', 'label' => 'admin.column.menu', 'path' => 'menu', 'type' => 'text', 'sort' => 'e.menu'],
                ['key' => 'position', 'label' => 'admin.column.position', 'path' => 'position', 'type' => 'text', 'sort' => 'e.position'],
            ], searchFields: ['menu'], translationSearchFields: ['label'], defaultSort: 'e.position', translatable: true),
            new AdminResource('categories', 'structure', 'admin.resource.categories', 'ri:price-tag-3-line', Category::class, 'ROLE_ADMIN', [
                $title('name'),
                ['key' => 'position', 'label' => 'admin.column.position', 'path' => 'position', 'type' => 'text', 'sort' => 'e.position'],
            ], translationSearchFields: ['name'], defaultSort: 'e.position', translatable: true),
            new AdminResource('settings', 'structure', 'admin.resource.settings', 'ri:settings-3-line', Setting::class, 'ROLE_SUPER_ADMIN', [
                ['key' => 'name', 'label' => 'admin.column.name', 'path' => 'name', 'type' => 'text', 'sort' => 'e.name'],
                ['key' => 'value', 'label' => 'admin.column.value', 'path' => 'value', 'type' => 'title'],
                $updated,
            ], searchFields: ['name'], translationSearchFields: ['value'], defaultSort: 'e.name', translatable: true),

            new AdminResource('users', 'users', 'admin.resource.users', 'ri:team-line', User::class, 'ROLE_SUPER_ADMIN', [
                ['key' => 'name', 'label' => 'admin.column.name', 'path' => 'name', 'type' => 'text', 'sort' => 'e.name'],
                ['key' => 'email', 'label' => 'admin.column.email', 'path' => 'email', 'type' => 'text', 'sort' => 'e.email'],
                ['key' => 'roles', 'label' => 'admin.column.roles', 'path' => 'roles', 'type' => 'roles'],
                ['key' => 'active', 'label' => 'admin.column.active', 'path' => 'active', 'type' => 'bool', 'sort' => 'e.active'],
                ['key' => 'lastLoginAt', 'label' => 'admin.column.last_access', 'path' => 'lastLoginAt', 'type' => 'datetime', 'sort' => 'e.lastLoginAt'],
            ], searchFields: ['name', 'email'], defaultSort: 'e.name', newRoute: 'app_admin_user_new', editRoute: 'app_admin_user_edit'),
        ];

        $indexed = [];
        foreach ($resources as $resource) {
            $indexed[$resource->id] = $resource;
        }

        return $indexed;
    }
}
