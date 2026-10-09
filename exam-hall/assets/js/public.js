(function () {
    'use strict';

    var configEl = document.getElementById('eh-exam-config');
    if (!configEl) {
        return;
    }

    var config = JSON.parse(configEl.textContent);
    var questions = Array.prototype.slice.call(document.querySelectorAll('.eh-q'));
    if (!questions.length) {
        return;
    }

    var indexKey = 'eh_q_index_' + config.attemptId;
    var index = 0;
    var stored = window.sessionStorage.getItem(indexKey);
    if (stored !== null) {
        var parsed = parseInt(stored, 10);
        if (!isNaN(parsed) && parsed >= 0 && parsed < questions.length) {
            index = parsed;
        }
    }

    var fill = document.getElementById('eh-progress-fill');
    var label = document.getElementById('eh-progress-label');
    var answeredEl = document.getElementById('eh-answered');
    var timerEl = document.getElementById('eh-timer');
    var saveEl = document.getElementById('eh-save-state');
    var live = document.getElementById('eh-live');
    var prev = document.getElementById('eh-prev');
    var next = document.getElementById('eh-next');
    var submit = document.getElementById('eh-submit');
    var modal = document.getElementById('eh-submit-modal');
    var modalCopy = document.getElementById('eh-submit-copy');
    var confirmBtn = document.getElementById('eh-submit-confirm');
    var cancelBtn = document.getElementById('eh-submit-cancel');
    var timeup = document.getElementById('eh-timeup');
    var dots = Array.prototype.slice.call(document.querySelectorAll('.eh-dot'));
    var endsAt = Date.now() + (config.remaining * 1000);
    var submitting = false;
    var originalTitle = document.title;

    function show(nextIndex) {
        index = Math.max(0, Math.min(questions.length - 1, nextIndex));
        window.sessionStorage.setItem(indexKey, String(index));
        questions.forEach(function (question, i) {
            question.classList.toggle('is-current', i === index);
        });
        dots.forEach(function (dot, i) {
            dot.classList.toggle('is-current', i === index);
            dot.setAttribute('aria-current', i === index ? 'true' : 'false');
        });
        if (fill) {
            fill.style.width = (((index + 1) / questions.length) * 100) + '%';
            fill.parentNode.setAttribute('aria-valuenow', String(index + 1));
            fill.parentNode.setAttribute('aria-valuemax', String(questions.length));
        }
        if (label) {
            label.textContent = String(config.progressLabel).replace('%1$d', String(index + 1)).replace('%2$d', String(questions.length));
        }
        if (prev) {
            prev.disabled = index === 0;
        }
        if (next) {
            next.hidden = index === questions.length - 1;
        }
        if (live) {
            live.textContent = 'Question ' + (index + 1) + ' of ' + questions.length;
        }
    }

    function answeredCount() {
        var count = 0;
        questions.forEach(function (question) {
            if (question.querySelector('input[type="radio"]:checked')) {
                count += 1;
            }
        });
        return count;
    }

    function paintAnswered() {
        var count = answeredCount();
        if (answeredEl) {
            answeredEl.textContent = String(config.answeredLabel).replace('%1$d', String(count)).replace('%2$d', String(questions.length));
        }
        questions.forEach(function (question, i) {
            if (!dots[i]) {
                return;
            }
            dots[i].classList.toggle('is-answered', !!question.querySelector('input[type="radio"]:checked'));
        });
    }

    function setSave(state) {
        if (!saveEl) {
            return;
        }
        saveEl.className = 'eh-save is-' + state;
        if (state === 'saving') {
            saveEl.textContent = 'Saving…';
        } else if (state === 'saved') {
            saveEl.textContent = 'Saved';
        } else if (state === 'error') {
            saveEl.textContent = 'Could not save';
        } else {
            saveEl.textContent = '';
        }
    }

    function post(action, fields) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', config.nonce);
        body.set('attempt_id', String(config.attemptId));
        Object.keys(fields).forEach(function (key) {
            var value = fields[key];
            if (value && typeof value === 'object') {
                Object.keys(value).forEach(function (inner) {
                    body.set(key + '[' + inner + ']', value[inner]);
                });
            } else {
                body.set(key, value);
            }
        });
        return window.fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) {
            return response.json();
        });
    }

    function collect() {
        var answers = {};
        questions.forEach(function (question) {
            var chosen = question.querySelector('input[type="radio"]:checked');
            if (chosen) {
                answers[question.getAttribute('data-id')] = chosen.value;
            }
        });
        return answers;
    }

    function goResult(data) {
        if (data && data.data && data.data.redirect) {
            submitting = true;
            window.sessionStorage.removeItem(indexKey);
            window.location.href = data.data.redirect;
            return true;
        }
        return false;
    }

    function saveQuestion(question) {
        var chosen = question.querySelector('input[type="radio"]:checked');
        if (!chosen || submitting) {
            return Promise.resolve();
        }
        setSave('saving');
        return post('eh_save_answer', {
            question_id: question.getAttribute('data-id'),
            choice: chosen.value
        }).then(function (data) {
            if (goResult(data)) {
                return;
            }
            if (!data || !data.success) {
                setSave('error');
                return;
            }
            setSave('saved');
        }).catch(function () {
            setSave('error');
        });
    }

    function formatTime(total) {
        total = Math.max(0, Math.floor(total));
        var hours = Math.floor(total / 3600);
        var minutes = Math.floor((total % 3600) / 60);
        var seconds = total % 60;
        function z(n) {
            return (n < 10 ? '0' : '') + n;
        }
        if (hours > 0) {
            return hours + ':' + z(minutes) + ':' + z(seconds);
        }
        return z(minutes) + ':' + z(seconds);
    }

    function submitExam(auto) {
        if (submitting) {
            return;
        }
        submitting = true;
        if (auto && timeup) {
            timeup.hidden = false;
        }
        if (modal) {
            modal.hidden = true;
        }
        setSave('saving');
        post('eh_submit_exam', {
            auto: auto ? '1' : '0',
            answers: collect()
        }).then(function (data) {
            if (!goResult(data)) {
                submitting = false;
                setSave('error');
            }
        }).catch(function () {
            submitting = false;
            setSave('error');
        });
    }

    function tick() {
        var remaining = Math.round((endsAt - Date.now()) / 1000);
        if (timerEl) {
            timerEl.textContent = formatTime(remaining);
            timerEl.classList.toggle('is-warning', remaining <= 300 && remaining > 60);
            timerEl.classList.toggle('is-danger', remaining <= 60);
        }
        if (remaining <= 300) {
            document.title = formatTime(Math.max(remaining, 0)) + ' · ' + originalTitle;
        }
        if (remaining <= 0) {
            submitExam(true);
        }
    }

    questions.forEach(function (question) {
        question.addEventListener('change', function (event) {
            if (!event.target || event.target.type !== 'radio') {
                return;
            }
            Array.prototype.forEach.call(question.querySelectorAll('.eh-option'), function (option) {
                var input = option.querySelector('input');
                option.classList.toggle('is-selected', !!(input && input.checked));
            });
            paintAnswered();
            saveQuestion(question);
        });
    });

    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            var target = parseInt(dot.getAttribute('data-jump'), 10);
            saveQuestion(questions[index]).then(function () {
                show(target);
            });
        });
    });

    if (prev) {
        prev.addEventListener('click', function () {
            saveQuestion(questions[index]).then(function () {
                show(index - 1);
            });
        });
    }
    if (next) {
        next.addEventListener('click', function () {
            saveQuestion(questions[index]).then(function () {
                show(index + 1);
            });
        });
    }
    if (submit) {
        submit.addEventListener('click', function () {
            var missing = questions.length - answeredCount();
            if (modalCopy) {
                modalCopy.textContent = missing > 0
                    ? missing + (missing === 1 ? ' question is' : ' questions are') + ' still unanswered and will score zero.'
                    : 'You have answered every question. You cannot change your answers after submission.';
            }
            if (modal) {
                modal.hidden = false;
                confirmBtn.focus();
            }
        });
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            if (modal) {
                modal.hidden = true;
            }
        });
    }
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            submitExam(false);
        });
    }

    var form = document.getElementById('eh-exam-form');
    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.altKey || event.metaKey || event.ctrlKey) {
            return;
        }
        if (modal && !modal.hidden) {
            return;
        }
        var n = parseInt(event.key, 10);
        if (n >= 1 && n <= 4) {
            var inputs = questions[index].querySelectorAll('input[type="radio"]');
            if (inputs[n - 1]) {
                inputs[n - 1].checked = true;
                inputs[n - 1].dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    });

    window.setInterval(tick, 250);
    window.setInterval(function () {
        if (submitting) {
            return;
        }
        post('eh_heartbeat', {}).then(function (data) {
            if (goResult(data)) {
                return;
            }
            if (data && data.success && typeof data.data.remaining === 'number') {
                endsAt = Date.now() + (data.data.remaining * 1000);
            }
        }).catch(function () {});
    }, 20000);

    window.addEventListener('beforeunload', function (event) {
        if (submitting) {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    });

    show(index);
    paintAnswered();
    tick();
}());
