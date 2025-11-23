// ===================
// Effects collection
// ===================
window.addEffect = function(trackIdx) {
    const container = document.getElementById('effects-' + trackIdx);
    if (!container) return;

    const index = parseInt(container.dataset.index || '0', 10);
    const proto = container.dataset.prototype.replace(/__name__/g, index);
    if (!proto) return;

    const wrap = document.createElement('div');
    wrap.className = 'effect-card';
    wrap.innerHTML = `
        <div class="effect-head">
            <div class="effect-title">${proto}</div>
            <div class="effect-actions">
                <button type="button" class="btn-mini js-effect-up">↑</button>
                <button type="button" class="btn-mini js-effect-down">↓</button>
                <button type="button" class="btn-mini danger js-effect-remove">Verwijder</button>
            </div>
        </div>
    `;

    container.appendChild(wrap);
    container.dataset.index = String(index + 1);

    wireEffectCard(wrap, container);
    renumberEffectPositions(container);

    function updatePositions(container) {
        container.querySelectorAll('.effect-card').forEach((card, i) => {
            const pos = card.querySelector('.effect-position');
            if (pos) pos.value = i;
        });
    }
};

function wireEffectCard(card, container) {
    card.querySelector('.js-effect-remove')?.addEventListener('click', () => {
        card.remove();
        renumberEffectPositions(container);
    });

    card.querySelector('.js-effect-up')?.addEventListener('click', () => {
        const prev = card.previousElementSibling;
        if (prev) {
            container.insertBefore(card, prev);
            renumberEffectPositions(container);
        }
    });

    card.querySelector('.js-effect-down')?.addEventListener('click', () => {
        const next = card.nextElementSibling;
        if (next) {
            container.insertBefore(next, card);
            renumberEffectPositions(container);
        }
    });
}

function renumberEffectPositions(container) {
    const cards = container.querySelectorAll('.effect-card');
    cards.forEach((c, i) => {
        const pos = c.querySelector('input.effect-position');
        if (pos) pos.value = String(i);
    });
}

// init bestaande cards
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.effects').forEach(container => {
        container.querySelectorAll('.effect-card').forEach(card => {
            wireEffectCard(card, container);
        });
        renumberEffectPositions(container);
    });
});
document.addEventListener('turbo:load', () => {
    document.querySelectorAll('.effects').forEach(container => {
        container.querySelectorAll('.effect-card').forEach(card => {
            wireEffectCard(card, container);
        });
        renumberEffectPositions(container);
    });
});
