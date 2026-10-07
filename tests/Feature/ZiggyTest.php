<?php

it('sert les routes en fichier mis en cache, sans cookie', function () {
    $reponse = $this->get('/ziggy.js?v=test');

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toContain('javascript')
        ->and($reponse->headers->get('Cache-Control'))->toContain('immutable')
        ->and($reponse->headers->getCookies())->toBeEmpty()
        ->and($reponse->getContent())->toStartWith('const Ziggy = ');
});
