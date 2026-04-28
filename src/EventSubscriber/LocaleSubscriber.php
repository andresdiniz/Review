<?php
// src/EventSubscriber/LocaleSubscriber.php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Detecta o idioma preferido do navegador (Accept-Language) e define o locale
 * do Symfony automaticamente. O usuário também pode forçar um locale via
 * query string (?_locale=en) ou sessão.
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    /** Locales suportados pelo site, em ordem de preferência */
    private const SUPPORTED = ['pt_BR', 'pt', 'en', 'es'];
    private const DEFAULT_LOCALE = 'pt_BR';

    public static function getSubscribedEvents(): array
    {
        // Prioridade alta para rodar antes do firewall / roteador
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->hasSession() ? $request->getSession() : null;

        // 1. Query string: ?_locale=en (tem prioridade máxima)
        if ($locale = $request->query->get('_locale')) {
            $locale = $this->normalize($locale);
            if (in_array($locale, self::SUPPORTED, true)) {
                if ($session) {
                    $session->set('_locale', $locale);
                }
                $request->setLocale($locale);
                return;
            }
        }

        // 2. Sessão persistida de visita anterior
        if ($session && $sessionLocale = $session->get('_locale')) {
            $locale = $this->normalize($sessionLocale);
            if (in_array($locale, self::SUPPORTED, true)) {
                $request->setLocale($locale);
                return;
            }
        }

        // 3. Accept-Language do navegador
        $preferred = $request->getPreferredLanguage(self::SUPPORTED);

        if ($preferred) {
            $locale = $this->normalize($preferred);
            if (in_array($locale, self::SUPPORTED, true)) {
                $request->setLocale($locale);
                return;
            }
        }

        // 4. Fallback padrão
        $request->setLocale(self::DEFAULT_LOCALE);
    }

    /**
     * Normaliza variações do locale para o formato suportado.
     * Ex: "pt-BR" → "pt_BR", "pt-br" → "pt_BR", "en-US" → "en"
     */
    private function normalize(string $locale): string
    {
        $locale = str_replace('-', '_', $locale);

        // Mapeamento explícito de variantes
        $map = [
            'pt_BR' => 'pt_BR',
            'pt'    => 'pt_BR',  // Português simples → pt_BR
            'en_US' => 'en',
            'en_GB' => 'en',
            'en'    => 'en',
            'es_MX' => 'es',
            'es_AR' => 'es',
            'es_ES' => 'es',
            'es'    => 'es',
        ];

        return $map[$locale] ?? strtolower(explode('_', $locale)[0]);
    }
}
