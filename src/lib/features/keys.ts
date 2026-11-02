/**
 * Static feature registry.
 *
 * This file is the single source of truth for feature keys, display names,
 * scopes and default values. The database only stores the current enabled
 * state (and publication overrides); it must not be used to invent new keys.
 *
 * `defaultEnabled` is informational metadata. It is NOT used as a fallback
 * when a FeatureFlag row is missing: missing rows always fail closed.
 */

export type FeatureScopeValue = "GLOBAL" | "GLOBAL_AND_PUBLICATION";

export type FeatureDefinition = {
  readonly name: string;
  readonly description: string;
  readonly scope: FeatureScopeValue;
  readonly defaultEnabled: boolean;
};

export const FEATURES = {
  login: {
    name: "Đăng nhập",
    description: "Cho phép người dùng đăng nhập vào hệ thống.",
    scope: "GLOBAL",
    defaultEnabled: true,
  },
  register: {
    name: "Đăng ký",
    description: "Cho phép tạo tài khoản mới.",
    scope: "GLOBAL",
    defaultEnabled: true,
  },
  profile: {
    name: "Hồ sơ cá nhân",
    description: "Cho phép xem và cập nhật hồ sơ cá nhân.",
    scope: "GLOBAL",
    defaultEnabled: true,
  },
  like: {
    name: "Thích",
    description: "Cho phép thích ấn phẩm.",
    scope: "GLOBAL_AND_PUBLICATION",
    defaultEnabled: true,
  },
  comment: {
    name: "Bình luận",
    description: "Cho phép bình luận trên ấn phẩm.",
    scope: "GLOBAL_AND_PUBLICATION",
    defaultEnabled: true,
  },
  favorite: {
    name: "Lưu ấn phẩm",
    description: "Cho phép lưu ấn phẩm vào danh sách cá nhân.",
    scope: "GLOBAL_AND_PUBLICATION",
    defaultEnabled: true,
  },
  history: {
    name: "Lịch sử đọc",
    description: "Cho phép ghi nhận và xem lịch sử đọc.",
    scope: "GLOBAL_AND_PUBLICATION",
    defaultEnabled: true,
  },
  share: {
    name: "Chia sẻ",
    description: "Cho phép chia sẻ ấn phẩm.",
    scope: "GLOBAL",
    defaultEnabled: true,
  },
  download: {
    name: "Tải xuống",
    description: "Cho phép tải ấn phẩm xuống.",
    scope: "GLOBAL",
    defaultEnabled: true,
  },
  print: {
    name: "In",
    description: "Cho phép in ấn phẩm.",
    scope: "GLOBAL",
    defaultEnabled: true,
  },
} as const satisfies Record<string, FeatureDefinition>;

export type FeatureKey = keyof typeof FEATURES;

export const FEATURE_KEYS = Object.keys(FEATURES) as readonly FeatureKey[];

export function isFeatureKey(value: unknown): value is FeatureKey {
  return (
    typeof value === "string" &&
    Object.prototype.hasOwnProperty.call(FEATURES, value)
  );
}

export function getFeatureDefinition(
  value: string
): FeatureDefinition | null {
  return isFeatureKey(value) ? FEATURES[value] : null;
}
