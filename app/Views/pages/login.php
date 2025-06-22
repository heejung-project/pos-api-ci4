<?= view('templates/header') ?>

<div class="auth-container">
    <h3 class="mb-4 text-center">로그인</h3>
    <form action="/login" method="post">
        <div class="mb-3">
            <label for="email" class="form-label">이메일</label>
            <input type="email" name="email" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">비밀번호</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">로그인</button>
    </form>
</div>

<?= view('templates/footer') ?>