<!-- 🐍 Playable Snake Loader -->
<div id="snake-loader">
    <canvas id="snakeCanvas" width="240" height="240"></canvas>
    <p class="snake-text">Use ↑ ↓ ← → to play while loading</p>
    <p class="snake-score">Score: <span id="score">0</span></p>
</div>

<script>
(function () {
    const loader = document.getElementById('snake-loader');
    const canvas = document.getElementById('snakeCanvas');
    const ctx = canvas.getContext('2d');
    const scoreEl = document.getElementById('score');

    const grid = 12;
    const tileCount = canvas.width / grid;

    let snake, dx, dy, food, score, gameOver;

    function resetGame() {
        snake = [{ x: 10, y: 10 }];
        dx = 1;
        dy = 0;
        score = 0;
        gameOver = false;
        scoreEl.textContent = score;

        food = {
            x: Math.floor(Math.random() * tileCount),
            y: Math.floor(Math.random() * tileCount)
        };
    }

    function draw() {
        if (gameOver) return;

        ctx.fillStyle = "#16213e";
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // Move
        const head = { x: snake[0].x + dx, y: snake[0].y + dy };

        // Wall wrap
        if (head.x < 0) head.x = tileCount - 1;
        if (head.y < 0) head.y = tileCount - 1;
        if (head.x >= tileCount) head.x = 0;
        if (head.y >= tileCount) head.y = 0;

        // Collision with self
        for (let part of snake) {
            if (part.x === head.x && part.y === head.y) {
                gameOver = true;
                setTimeout(resetGame, 1000); // auto restart
                return;
            }
        }

        snake.unshift(head);

        // Eat food
        if (head.x === food.x && head.y === food.y) {
            score++;
            scoreEl.textContent = score;

            food = {
                x: Math.floor(Math.random() * tileCount),
                y: Math.floor(Math.random() * tileCount)
            };
        } else {
            snake.pop();
        }

        // Draw snake (IceWind cyan)
        ctx.fillStyle = "#4cc9f0";
        snake.forEach(part => {
            ctx.fillRect(part.x * grid, part.y * grid, grid - 1, grid - 1);
        });

        // Draw food
        ctx.fillStyle = "#4361ee";
        ctx.fillRect(food.x * grid, food.y * grid, grid - 1, grid - 1);
    }

    // Controls
    document.addEventListener('keydown', function (e) {
        if (e.key === "ArrowUp" && dy === 0) {
            dx = 0; dy = -1;
        } else if (e.key === "ArrowDown" && dy === 0) {
            dx = 0; dy = 1;
        } else if (e.key === "ArrowLeft" && dx === 0) {
            dx = -1; dy = 0;
        } else if (e.key === "ArrowRight" && dx === 0) {
            dx = 1; dy = 0;
        }
    });

    setInterval(draw, 110);

    resetGame();

    /* SHOW / HIDE LOADER */
    window.showLoader = function () {
        loader.style.display = 'flex';
        setTimeout(() => loader.style.opacity = '1', 10);
    };

    window.hideLoader = function () {
        loader.style.opacity = '0';
        setTimeout(() => loader.style.display = 'none', 300);
    };

    /* AUTO HOOKS (same as your system) */

    window.addEventListener('pageshow', function () {
        hideLoader();
    });

    function isExportLink(el) {
        var href = el.getAttribute('href') || '';
        return href.indexOf('export=') !== -1;
    }

    function isModalToggle(el) {
        return el.hasAttribute('data-bs-toggle') &&
               el.getAttribute('data-bs-toggle') === 'modal';
    }

    function isModalDismiss(el) {
        return el.hasAttribute('data-bs-dismiss');
    }

    function isDeleteButton(btn) {
        return btn.getAttribute('onclick') &&
               btn.getAttribute('onclick').indexOf('confirm(') !== -1;
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        var action = (form.getAttribute('action') || '') + window.location.search;
        if (action.indexOf('export=') !== -1) return;

        var activeBtn = document.activeElement;
        if (activeBtn && isDeleteButton(activeBtn)) {
            showLoader();
            return;
        }

        showLoader();
    }, true);

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('button, a');
        if (!btn) return;

        if (isModalToggle(btn) || isModalDismiss(btn)) return;
        if (btn.tagName === 'A' && isExportLink(btn)) return;
        if (btn.classList.contains('btn-close')) return;

        if (btn.tagName === 'A' && btn.classList.contains('nav-link')) {
            showLoader();
            return;
        }

        if (btn.tagName === 'A' && btn.classList.contains('navbar-brand')) {
            showLoader();
            return;
        }

        if (btn.tagName === 'A' && (btn.getAttribute('href') || '').indexOf('logout') !== -1) {
            showLoader();
            return;
        }

        if (btn.tagName === 'A' && btn.classList.contains('nav-link-item')) {
            showLoader();
            return;
        }
    });

})();
</script>