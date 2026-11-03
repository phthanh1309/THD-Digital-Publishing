"use server";

import { revalidatePath } from "next/cache";
import { requireAdmin } from "@/lib/authorization";
import {
  getFeatureDefinitionForManagement,
  runSetGlobalFeature,
  runSetPublicationOverride,
} from "@/lib/features/management";
import { createPrismaFeatureManagementDependencies } from "@/lib/features/management-prisma";

export type FeatureActionState = {
  status: "idle" | "success" | "error";
  message: string;
  featureKey?: string;
  publicationId?: string;
};

function errorMessage(error: unknown): string {
  if (error instanceof Error && error.message) return error.message;
  return "Không thể cập nhật Feature Flag. Vui lòng thử lại.";
}

function managementDependencies() {
  return createPrismaFeatureManagementDependencies({
    revalidate(paths) {
      for (const path of paths) revalidatePath(path);
    },
  });
}

export async function setGlobalFeatureAction(
  _previousState: FeatureActionState,
  formData: FormData
): Promise<FeatureActionState> {
  const featureKey = String(formData.get("featureKey") ?? "");

  try {
    await runSetGlobalFeature(
      {
        featureKey,
        enabled: formData.get("enabled"),
      },
      {
        ...managementDependencies(),
        requireAdmin,
      }
    );

    const name = getFeatureDefinitionForManagement(featureKey)?.name;
    return {
      status: "success",
      message: `Đã cập nhật ${name ?? "Feature Flag"}.`,
      featureKey,
    };
  } catch (error) {
    return {
      status: "error",
      message: errorMessage(error),
      featureKey,
    };
  }
}

export async function setPublicationOverrideAction(
  _previousState: FeatureActionState,
  formData: FormData
): Promise<FeatureActionState> {
  const featureKey = String(formData.get("featureKey") ?? "");
  const publicationId = String(formData.get("publicationId") ?? "");

  try {
    await runSetPublicationOverride(
      {
        featureKey,
        publicationId,
        state: formData.get("state"),
      },
      {
        ...managementDependencies(),
        requireAdmin,
      }
    );

    const name = getFeatureDefinitionForManagement(featureKey)?.name;
    return {
      status: "success",
      message: `Đã cập nhật override ${name ?? "Feature Flag"} cho ấn phẩm.`,
      featureKey,
      publicationId,
    };
  } catch (error) {
    return {
      status: "error",
      message: errorMessage(error),
      featureKey,
      publicationId,
    };
  }
}
