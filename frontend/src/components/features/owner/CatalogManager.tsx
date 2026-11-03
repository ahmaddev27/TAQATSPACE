"use client";

import { useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { DataTable, type DataTableColumn } from "@/components/ui/DataTable";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Select } from "@/components/ui/Select";
import { Tabs } from "@/components/ui/Tabs";
import {
  archiveCatalogCategory,
  archiveCatalogItem,
  createCatalogCategory,
  createCatalogItem,
  reorderCatalogCategories,
  updateCatalogCategory,
  updateCatalogItem,
} from "@/lib/actions/finance";
import {
  CATALOG_KINDS,
  CATALOG_UNITS,
  type CatalogCategory,
  type CatalogItem,
  type CatalogKind,
  type CatalogUnit,
} from "@/lib/types/finance";
import { money } from "./format";

interface Props {
  categories: CatalogCategory[];
  items: CatalogItem[];
}

type Tab = "items" | "categories";

type Editor =
  | { type: "item"; item: CatalogItem | null }
  | { type: "category"; category: CatalogCategory | null }
  | null;

/** Localised display name of a catalog record. */
function useDisplayName() {
  const locale = useLocale();
  return (record: { name_ar: string; name_en: string }) =>
    locale === "ar" ? record.name_ar : record.name_en;
}

/** Owner finance catalog: items and categories with create, edit, archive and reorder. */
export function CatalogManager({ categories, items }: Props) {
  const t = useTranslations("finance.catalog");
  const [tab, setTab] = useState<Tab>("items");
  const [editor, setEditor] = useState<Editor>(null);

  return (
    <section className="card card-pad stack" style={{ gap: 16 }}>
      <div className="between row wrap" style={{ gap: 10 }}>
        <Tabs
          items={[
            { id: "items", label: t("tabs.items") },
            { id: "categories", label: t("tabs.categories") },
          ]}
          value={tab}
          onChange={(id) => setTab(id as Tab)}
        />
        <Button
          variant="primary"
          icon="plus"
          onClick={() =>
            setEditor(
              tab === "items"
                ? { type: "item", item: null }
                : { type: "category", category: null },
            )
          }
        >
          {tab === "items" ? t("items.add") : t("categories.add")}
        </Button>
      </div>

      {tab === "items" ? (
        <ItemsTable items={items} onEdit={(item) => setEditor({ type: "item", item })} />
      ) : (
        <CategoriesTable
          categories={categories}
          onEdit={(category) => setEditor({ type: "category", category })}
        />
      )}

      {editor?.type === "item" && (
        <ItemModal
          item={editor.item}
          categories={categories}
          onClose={() => setEditor(null)}
        />
      )}
      {editor?.type === "category" && (
        <CategoryModal category={editor.category} onClose={() => setEditor(null)} />
      )}
    </section>
  );
}

function ItemsTable({
  items,
  onEdit,
}: {
  items: CatalogItem[];
  onEdit: (item: CatalogItem) => void;
}) {
  const t = useTranslations("finance.catalog");
  const name = useDisplayName();
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();

  const onArchive = async (item: CatalogItem) => {
    const ok = await confirm({
      title: t("items.archiveTitle"),
      message: t("items.archiveConfirm", { name: name(item) }),
      confirmLabel: t("archive"),
      tone: "danger",
      icon: "trash",
    });
    if (!ok) return;

    startTransition(async () => {
      const res = await archiveCatalogItem(item.id);
      toast(
        res.ok
          ? { tone: "ok", title: t("items.archived") }
          : { tone: "err", title: t("error"), body: res.message },
      );
    });
  };

  const columns: DataTableColumn<CatalogItem>[] = [
    { id: "code", header: t("fields.code"), cell: (i) => <span className="ltr">{i.code}</span> },
    { id: "name", header: t("fields.name"), cell: (i) => name(i) },
    { id: "category", header: t("fields.category"), cell: (i) => (i.category ? name(i.category) : "—") },
    { id: "unit", header: t("fields.unit"), cell: (i) => t(`units.${i.unit}`) },
    { id: "price", header: t("fields.price"), num: true, cell: (i) => money(i.price) },
    {
      id: "actions",
      header: "",
      cell: (i) => (
        <div className="row" style={{ gap: 6 }}>
          <Button variant="secondary" size="sm" icon="edit" aria-label={t("edit")} onClick={() => onEdit(i)} />
          <Button variant="ghost" size="sm" icon="trash" aria-label={t("archive")} loading={pending} onClick={() => onArchive(i)} />
        </div>
      ),
    },
  ];

  return (
    <DataTable
      columns={columns}
      rows={items}
      rowKey={(i) => i.id}
      empty={<p className="muted" style={{ margin: 0 }}>{t("items.empty")}</p>}
    />
  );
}

function CategoriesTable({
  categories,
  onEdit,
}: {
  categories: CatalogCategory[];
  onEdit: (category: CatalogCategory) => void;
}) {
  const t = useTranslations("finance.catalog");
  const name = useDisplayName();
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();

  const report = (res: { ok: boolean; message?: string }, okTitle: string) =>
    toast(
      res.ok
        ? { tone: "ok", title: okTitle }
        : { tone: "err", title: t("error"), body: res.message },
    );

  const move = (index: number, delta: -1 | 1) => {
    const ids = categories.map((c) => c.id);
    [ids[index], ids[index + delta]] = [ids[index + delta], ids[index]];
    startTransition(async () => report(await reorderCatalogCategories(ids), t("categories.reordered")));
  };

  const onArchive = async (category: CatalogCategory) => {
    const ok = await confirm({
      title: t("categories.archiveTitle"),
      message: t("categories.archiveConfirm", { name: name(category) }),
      confirmLabel: t("archive"),
      tone: "danger",
      icon: "trash",
    });
    if (!ok) return;

    startTransition(async () => report(await archiveCatalogCategory(category.id), t("categories.archived")));
  };

  const columns: DataTableColumn<CatalogCategory>[] = [
    { id: "code", header: t("fields.code"), cell: (c) => <span className="ltr">{c.code}</span> },
    { id: "name", header: t("fields.name"), cell: (c) => name(c) },
    {
      id: "kind",
      header: t("fields.kind"),
      cell: (c) => <Badge tone={c.kind === "sales" ? "success" : "neutral"}>{t(`kinds.${c.kind}`)}</Badge>,
    },
    {
      id: "actions",
      header: "",
      cell: (c) => {
        const index = categories.findIndex((x) => x.id === c.id);
        return (
          <div className="row" style={{ gap: 6 }}>
            <Button variant="ghost" size="sm" icon="arrowUp" aria-label={t("categories.moveUp")} disabled={pending || index === 0} onClick={() => move(index, -1)} />
            <Button variant="ghost" size="sm" icon="arrowDown" aria-label={t("categories.moveDown")} disabled={pending || index === categories.length - 1} onClick={() => move(index, 1)} />
            <Button variant="secondary" size="sm" icon="edit" aria-label={t("edit")} onClick={() => onEdit(c)} />
            <Button variant="ghost" size="sm" icon="trash" aria-label={t("archive")} loading={pending} onClick={() => onArchive(c)} />
          </div>
        );
      },
    },
  ];

  return (
    <DataTable
      columns={columns}
      rows={categories}
      rowKey={(c) => c.id}
      empty={<p className="muted" style={{ margin: 0 }}>{t("categories.empty")}</p>}
    />
  );
}

/** Create/edit item form. `item === null` means create; the code is fixed once created. */
function ItemModal({
  item,
  categories,
  onClose,
}: {
  item: CatalogItem | null;
  categories: CatalogCategory[];
  onClose: () => void;
}) {
  const t = useTranslations("finance.catalog");
  const name = useDisplayName();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const [categoryId, setCategoryId] = useState(item?.catalog_category_id ?? categories[0]?.id ?? "");
  const [code, setCode] = useState(item?.code ?? "");
  const [nameAr, setNameAr] = useState(item?.name_ar ?? "");
  const [nameEn, setNameEn] = useState(item?.name_en ?? "");
  const [unit, setUnit] = useState<CatalogUnit>(item?.unit ?? "piece");
  const [price, setPrice] = useState(item?.price ?? "");

  const submit = () =>
    startTransition(async () => {
      const base = {
        catalog_category_id: categoryId,
        name_ar: nameAr,
        name_en: nameEn,
        unit,
        price: Number(price),
      };
      const res = item
        ? await updateCatalogItem(item.id, base)
        : await createCatalogItem({ ...base, code });

      if (res.ok) {
        toast({ tone: "ok", title: item ? t("items.updated") : t("items.created") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });

  return (
    <Modal
      title={item ? t("items.editTitle") : t("items.createTitle")}
      icon="receipt"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t("cancel")}</Button>
          <Button variant="primary" loading={pending} onClick={submit}>{t("save")}</Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 14 }}>
        <Field label={t("fields.category")} error={errors.catalog_category_id?.[0]}>
          <Select value={categoryId} onChange={(e) => setCategoryId(e.target.value)}>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>{name(c)}</option>
            ))}
          </Select>
        </Field>
        <Field
          label={t("fields.code")}
          hint={item ? t("codeLocked") : undefined}
          error={errors.code?.[0]}
        >
          <Input className="ltr" value={code} disabled={item !== null} onChange={(e) => setCode(e.target.value)} />
        </Field>
        <div className="row wrap" style={{ gap: 12 }}>
          <Field label={t("fields.nameAr")} error={errors.name_ar?.[0]} className="grow">
            <Input value={nameAr} onChange={(e) => setNameAr(e.target.value)} />
          </Field>
          <Field label={t("fields.nameEn")} error={errors.name_en?.[0]} className="grow">
            <Input className="ltr" value={nameEn} onChange={(e) => setNameEn(e.target.value)} />
          </Field>
        </div>
        <div className="row wrap" style={{ gap: 12 }}>
          <Field label={t("fields.unit")} error={errors.unit?.[0]} className="grow">
            <Select value={unit} onChange={(e) => setUnit(e.target.value as CatalogUnit)}>
              {CATALOG_UNITS.map((u) => (
                <option key={u} value={u}>{t(`units.${u}`)}</option>
              ))}
            </Select>
          </Field>
          <Field label={t("fields.price")} error={errors.price?.[0]} className="grow">
            <Input type="number" min={0} step="0.01" className="ltr" value={price} onChange={(e) => setPrice(e.target.value)} />
          </Field>
        </div>
      </div>
    </Modal>
  );
}

/** Create/edit category form. `category === null` means create; code and kind are fixed once created. */
function CategoryModal({
  category,
  onClose,
}: {
  category: CatalogCategory | null;
  onClose: () => void;
}) {
  const t = useTranslations("finance.catalog");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const [kind, setKind] = useState<CatalogKind>(category?.kind ?? "sales");
  const [code, setCode] = useState(category?.code ?? "");
  const [nameAr, setNameAr] = useState(category?.name_ar ?? "");
  const [nameEn, setNameEn] = useState(category?.name_en ?? "");

  const submit = () =>
    startTransition(async () => {
      const names = { name_ar: nameAr, name_en: nameEn };
      const res = category
        ? await updateCatalogCategory(category.id, names)
        : await createCatalogCategory({ ...names, kind, code });

      if (res.ok) {
        toast({ tone: "ok", title: category ? t("categories.updated") : t("categories.created") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });

  return (
    <Modal
      title={category ? t("categories.editTitle") : t("categories.createTitle")}
      icon="layers"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t("cancel")}</Button>
          <Button variant="primary" loading={pending} onClick={submit}>{t("save")}</Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 14 }}>
        <Field label={t("fields.kind")} error={errors.kind?.[0]}>
          <Select value={kind} disabled={category !== null} onChange={(e) => setKind(e.target.value as CatalogKind)}>
            {CATALOG_KINDS.map((k) => (
              <option key={k} value={k}>{t(`kinds.${k}`)}</option>
            ))}
          </Select>
        </Field>
        <Field
          label={t("fields.code")}
          hint={category ? t("codeLocked") : undefined}
          error={errors.code?.[0]}
        >
          <Input className="ltr" value={code} disabled={category !== null} onChange={(e) => setCode(e.target.value)} />
        </Field>
        <div className="row wrap" style={{ gap: 12 }}>
          <Field label={t("fields.nameAr")} error={errors.name_ar?.[0]} className="grow">
            <Input value={nameAr} onChange={(e) => setNameAr(e.target.value)} />
          </Field>
          <Field label={t("fields.nameEn")} error={errors.name_en?.[0]} className="grow">
            <Input className="ltr" value={nameEn} onChange={(e) => setNameEn(e.target.value)} />
          </Field>
        </div>
      </div>
    </Modal>
  );
}
