import api from "./api.js";

export default {
  list(params = {}) { return api.get("/notifications", { params }); },
  unreadCount() { return api.get("/notifications/unread-count"); },
  markRead(id) { return api.post(`/notifications/mark-read/${id}`); },
  markAllRead() { return api.post("/notifications/mark-all-read"); },
};
