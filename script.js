const form = document.querySelector("#booking-form");
const success = document.querySelector("#form-success");
const reset = document.querySelector("#form-reset");

form?.addEventListener("submit", (event) => {
  event.preventDefault();
  form.hidden = true;
  success.hidden = false;
});

reset?.addEventListener("click", () => {
  form.reset();
  success.hidden = true;
  form.hidden = false;
  form.querySelector("input")?.focus();
});
