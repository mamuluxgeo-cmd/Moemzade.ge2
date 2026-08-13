<?php
$faqs = [
    [t('faq.q1'), t('faq.a1')],
    [t('faq.q2'), t('faq.a2')],
    [t('faq.q3'), t('faq.a3')],
    [t('faq.q4'), t('faq.a4')],
    [t('faq.q5'), t('faq.a5')],
    [t('faq.q6'), t('faq.a6')],
    [t('faq.q7'), t('faq.a7')],
    [t('faq.q8'), t('faq.a8')],
];
?>
<header class="page-hero"><div class="container narrow"><span class="section-kicker">FAQ</span><h1><?= e(t('faq.title')) ?></h1><p><?= e(t('faq.subtitle')) ?></p></div></header>
<section class="section faq-section"><div class="container narrow"><div class="faq-list">
    <?php foreach ($faqs as [$question, $answer]): ?><details><summary><?= e($question) ?><span>+</span></summary><p><?= e($answer) ?></p></details><?php endforeach; ?>
</div><div class="faq-action"><h2><?= e(t('faq.ready')) ?></h2><a class="button button-primary" href="/register"><?= e(t('register.title')) ?></a><a class="button button-muted" href="/mentor-requests/new"><?= e(t('mentor.add_request')) ?></a><a class="button button-muted" href="/teachers"><?= e(t('search.title')) ?></a></div></div></section>
