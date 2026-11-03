"use client";

import { useActionState } from "react";
import {
  setGlobalFeatureAction,
  setPublicationOverrideAction,
  type FeatureActionState,
} from "./actions";

const initialFeatureActionState: FeatureActionState = {
  status: "idle",
  message: "",
};

function Feedback({ state }: { state: FeatureActionState }) {
  if (state.status === "idle" || !state.message) return null;

  return (
    <p
      className={`feature-feedback ${
        state.status === "error" ? "is-error" : "is-success"
      }`}
      role={state.status === "error" ? "alert" : "status"}
    >
      {state.message}
    </p>
  );
}

export function GlobalFeatureToggle({
  featureKey,
  enabled,
}: {
  featureKey: string;
  enabled: boolean;
}) {
  const [state, formAction, pending] = useActionState(
    setGlobalFeatureAction,
    initialFeatureActionState
  );

  return (
    <div className="feature-toggle-block">
      <form action={formAction}>
        <input type="hidden" name="featureKey" value={featureKey} />
        <input
          type="hidden"
          name="enabled"
          value={enabled ? "false" : "true"}
        />
        <button
          type="submit"
          role="switch"
          aria-checked={enabled}
          aria-label={enabled ? "Tắt feature" : "Bật feature"}
          className={`feature-switch${enabled ? " is-on" : ""}`}
          disabled={pending}
        >
          <span className="feature-switch-track" aria-hidden="true">
            <span className="feature-switch-thumb" />
          </span>
          <span className="feature-switch-label">
            {pending ? "Đang cập nhật..." : enabled ? "Đang bật" : "Đang tắt"}
          </span>
        </button>
      </form>
      <Feedback state={state} />
    </div>
  );
}

const OVERRIDE_STATES = [
  { value: "INHERIT", label: "INHERIT — theo Global" },
  { value: "ENABLED", label: "ENABLED — bật cho ấn phẩm" },
  { value: "DISABLED", label: "DISABLED — tắt cho ấn phẩm" },
] as const;

export function PublicationOverrideForm({
  featureKey,
  publicationId,
  currentState,
  globalEnabled,
}: {
  featureKey: string;
  publicationId: string;
  currentState: "INHERIT" | "ENABLED" | "DISABLED";
  globalEnabled: boolean;
}) {
  const [state, formAction, pending] = useActionState(
    setPublicationOverrideAction,
    initialFeatureActionState
  );

  return (
    <div className="feature-override-block">
      <form action={formAction} className="feature-override-form">
        <input type="hidden" name="featureKey" value={featureKey} />
        <input type="hidden" name="publicationId" value={publicationId} />
        <label className="feature-override-label">
          <span>Trạng thái override</span>
          <select name="state" defaultValue={currentState} disabled={pending}>
            {OVERRIDE_STATES.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>
        <button
          type="submit"
          className="button button-secondary button-sm"
          disabled={pending}
        >
          {pending ? "Đang lưu..." : "Lưu override"}
        </button>
      </form>

      {!globalEnabled && (
        <p className="feature-note">
          Global đang tắt: override ENABLED không thể bật lại tính năng này.
        </p>
      )}

      <Feedback state={state} />
    </div>
  );
}
