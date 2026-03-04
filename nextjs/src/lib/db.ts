import mysql from "mysql2/promise";
import type { RowDataPacket, ResultSetHeader, PoolConnection } from "mysql2";

const globalForPool = globalThis as unknown as { dbPool: mysql.Pool | undefined };

export const pool =
  globalForPool.dbPool ??
  mysql.createPool({
    host: process.env.DB_HOST || "localhost",
    port: parseInt(process.env.DB_PORT || "3306"),
    user: process.env.DB_USER || "reviewflow_user",
    password: process.env.DB_PASS || "",
    database: process.env.DB_NAME || "reviewflow",
    charset: "utf8mb4",
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
    enableKeepAlive: true,
    keepAliveInitialDelay: 0,
  });

if (process.env.NODE_ENV !== "production") globalForPool.dbPool = pool;

/** Run a SELECT query and return all matching rows. */
export async function query<T extends RowDataPacket>(
  sql: string,
  params?: unknown[]
): Promise<T[]> {
  const [rows] = await pool.query<T[]>(sql, params);
  return rows;
}

/** Run a SELECT query and return the first matching row (or null). */
export async function queryOne<T extends RowDataPacket>(
  sql: string,
  params?: unknown[]
): Promise<T | null> {
  const rows = await query<T>(sql, params);
  return rows[0] ?? null;
}

/** Run an INSERT / UPDATE / DELETE and return the ResultSetHeader. */
export async function execute(
  sql: string,
  params?: unknown[]
): Promise<ResultSetHeader> {
  const [result] = await pool.execute<ResultSetHeader>(sql, params);
  return result;
}

/** Run multiple operations inside a single transaction. */
export async function transaction<T>(
  fn: (conn: PoolConnection) => Promise<T>
): Promise<T> {
  const conn = await pool.getConnection();
  await conn.beginTransaction();
  try {
    const result = await fn(conn);
    await conn.commit();
    return result;
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

export type { RowDataPacket, ResultSetHeader, PoolConnection };
