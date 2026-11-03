import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { CatalogManager } from "./CatalogManager";
import {
  createCatalogCategory,
  createCatalogItem,
  reorderCatalogCategories,
  updateCatalogItem,
} from "@/lib/actions/finance";
import type { CatalogCategory, CatalogItem } from "@/lib/types/finance";

vi.mock("@/lib/actions/finance", () => ({
  createCatalogCategory: vi.fn().mockResolvedValue({ ok: true }),
  updateCatalogCategory: vi.fn().mockResolvedValue({ ok: true }),
  archiveCatalogCategory: vi.fn().mockResolvedValue({ ok: true }),
  reorderCatalogCategories: vi.fn().mockResolvedValue({ ok: true }),
  createCatalogItem: vi.fn().mockResolvedValue({ ok: true }),
  updateCatalogItem: vi.fn().mockResolvedValue({ ok: true }),
  archiveCatalogItem: vi.fn().mockResolvedValue({ ok: true }),
}));

vi.mock("@/components/providers/ToastProvider", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));

vi.mock("@/components/ui/ConfirmDialog", () => ({
  useConfirm: () => vi.fn().mockResolvedValue(true),
}));

const category = (over: Partial<CatalogCategory>): CatalogCategory => ({
  id: "c1",
  kind: "sales",
  code: "DRINKS",
  name_ar: "مشروبات",
  name_en: "Drinks",
  sort_order: 0,
  is_archived: false,
  archived_at: null,
  created_at: null,
  ...over,
});

const drinks = category({});
const snacks = category({ id: "c2", code: "SNACKS", name_en: "Snacks", sort_order: 1 });

const coffee: CatalogItem = {
  id: "i1",
  catalog_category_id: "c1",
  category: drinks,
  code: "COFFEE",
  name_ar: "قهوة",
  name_en: "Coffee",
  unit: "piece",
  price: "7.50",
  is_archived: false,
  archived_at: null,
  created_at: null,
};

describe("CatalogManager", () => {
  beforeEach(() => vi.clearAllMocks());

  it("lists items with code, category and price", () => {
    render(<CatalogManager categories={[drinks, snacks]} items={[coffee]} />);

    expect(screen.getByText("COFFEE")).toBeInTheDocument();
    expect(screen.getByText("Coffee")).toBeInTheDocument();
    expect(screen.getByText("Drinks")).toBeInTheDocument();
    expect(screen.getByText("₪7.50")).toBeInTheDocument();
  });

  it("creates an item with the chosen category and unit", async () => {
    const user = userEvent.setup();
    render(<CatalogManager categories={[drinks, snacks]} items={[]} />);

    await user.click(screen.getByRole("button", { name: "items.add" }));
    const [code, nameAr, nameEn] = screen.getAllByRole("textbox");
    await user.type(code, "TEA");
    await user.type(nameAr, "شاي");
    await user.type(nameEn, "Tea");
    await user.type(screen.getByRole("spinbutton"), "3");
    await user.click(screen.getByRole("button", { name: "save" }));

    expect(createCatalogItem).toHaveBeenCalledWith({
      catalog_category_id: "c1",
      code: "TEA",
      name_ar: "شاي",
      name_en: "Tea",
      unit: "piece",
      price: 3,
    });
  });

  it("locks the code when editing an item and omits it from the update", async () => {
    const user = userEvent.setup();
    render(<CatalogManager categories={[drinks]} items={[coffee]} />);

    await user.click(screen.getByRole("button", { name: "edit" }));
    expect(screen.getAllByRole("textbox")[0]).toBeDisabled();
    await user.click(screen.getByRole("button", { name: "save" }));

    expect(updateCatalogItem).toHaveBeenCalledTimes(1);
    expect(vi.mocked(updateCatalogItem).mock.calls[0][1]).not.toHaveProperty("code");
  });

  it("creates a category from the categories tab", async () => {
    const user = userEvent.setup();
    render(<CatalogManager categories={[]} items={[]} />);

    await user.click(screen.getByRole("tab", { name: "tabs.categories" }));
    await user.click(screen.getByRole("button", { name: "categories.add" }));
    const [code, nameAr, nameEn] = screen.getAllByRole("textbox");
    await user.type(code, "SERVICES");
    await user.type(nameAr, "خدمات");
    await user.type(nameEn, "Services");
    await user.click(screen.getByRole("button", { name: "save" }));

    expect(createCatalogCategory).toHaveBeenCalledWith({
      kind: "sales",
      code: "SERVICES",
      name_ar: "خدمات",
      name_en: "Services",
    });
  });

  it("sends the full new order when a category is moved down", async () => {
    const user = userEvent.setup();
    render(<CatalogManager categories={[drinks, snacks]} items={[]} />);

    await user.click(screen.getByRole("tab", { name: "tabs.categories" }));
    await user.click(screen.getAllByRole("button", { name: "categories.moveDown" })[0]);

    expect(reorderCatalogCategories).toHaveBeenCalledWith(["c2", "c1"]);
  });
});
