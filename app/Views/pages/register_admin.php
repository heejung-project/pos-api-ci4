<?= view('templates/header') ?>

<main class="container mt-5" style="max-width: 500px;">
    <h2 class="mb-4">회원가입</h2>

    <form method="post" action="/signup">
      <div class="mb-3">
        <label for="username" class="form-label">사용자 이름</label>
        <input type="text" class="form-control" id="username" name="username" required>
      </div>

      <div class="mb-3">
        <label for="email" class="form-label">이메일</label>
        <input type="email" class="form-control" id="email" name="email" required>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">비밀번호</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>

      <div class="mb-3">
        <label for="confirm_password" class="form-label">비밀번호 확인</label>
        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
      </div>

      <button type="submit" class="btn btn-primary w-100">가입하기</button>
    </form>
  </main>

  <?= view('templates/footer') ?>