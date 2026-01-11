// Composables/useSnackbar.js
import { reactive } from "vue";

const snackbar = reactive({
  show: false,
  message: "",
  type: "success",
});

export function useSnackbar() {
  const show = (message, type = "success") => {
    snackbar.message = message;
    snackbar.type = type;
    snackbar.show = true;
    setTimeout(() => (snackbar.show = false), 3000);
  };

  return { snackbar, show };
}
